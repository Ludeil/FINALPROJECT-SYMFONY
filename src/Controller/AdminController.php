<?php

namespace App\Controller;

use App\Entity\Dormitory;
use App\Entity\Room;
use App\Entity\RoomApplication;
use App\Entity\User;
use App\Entity\ViewingRequest;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminController extends AbstractController
{
    private const ROOM_TYPES = [
        '4_person' => '4 person',
        'female_exclusive' => 'Female exclusive',
        'other' => 'Other',
    ];

    private const ROOM_STATUSES = [
        'available',
        'full',
        'maintenance',
        'inactive',
    ];

    private const DORMITORY_STATUSES = [
        'active',
        'inactive',
    ];

    private const VIEWING_REQUEST_STATUSES = [
        'pending',
        'confirmed',
        'completed',
        'cancelled',
    ];

    private const ROOM_APPLICATION_STATUSES = [
        'pending',
        'approved',
        'rejected',
        'cancelled',
        'moved_out',
    ];

    private const USER_ROLES = [
        'applicant',
        'resident',
        'admin',
    ];

    private const ACCOUNT_STATUSES = [
        'active',
        'inactive',
        'suspended',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $pendingViewings = $this->entityManager->getRepository(ViewingRequest::class)->count(['status' => 'pending']);
        $pendingApplications = $this->entityManager->getRepository(RoomApplication::class)->count(['status' => 'pending']);

        $availableRooms = (int) $this->entityManager
            ->getRepository(Room::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->andWhere('r.availableSlots > 0')
            ->setParameter('status', 'available')
            ->getQuery()
            ->getSingleScalarResult();

        $activeCustomers = (int) $this->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.role IN (:roles)')
            ->andWhere('u.accountStatus = :status')
            ->setParameter('roles', ['applicant', 'resident'])
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/dashboard.html.twig', [
            'title' => 'Dashboard',
            'pending_viewings' => $pendingViewings,
            'pending_applications' => $pendingApplications,
            'active_customers' => $activeCustomers,
            'available_rooms' => $availableRooms,
        ]);
    }

    #[Route('/admin/dormitories', name: 'admin_dormitories', methods: ['GET', 'POST'])]
    public function dormitories(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->validCsrf('dormitory_action', $request)) {
                return $this->redirectWithError('admin_dormitories', 'The form security token is invalid. Please try again.');
            }

            $action = (string) $request->request->get('action', '');
            $id = filter_var($request->request->get('id'), FILTER_VALIDATE_INT);

            try {
                if ($action === 'delete') {
                    if (!$id) {
                        throw new \RuntimeException('Dormitory ID is missing.');
                    }

                    $dormitory = $this->entityManager->getRepository(Dormitory::class)->find($id);
                    if (!$dormitory) {
                        throw new \RuntimeException('Dormitory not found.');
                    }

                    $roomCount = $this->entityManager->getRepository(Room::class)->count(['dormitory' => $dormitory]);
                    if ($roomCount > 0) {
                        throw new \RuntimeException('This dormitory has rooms assigned to it. Move or delete those rooms first, then delete the dormitory.');
                    }

                    $viewingCount = $this->entityManager->getRepository(ViewingRequest::class)->count(['dormitory' => $dormitory]);
                    if ($viewingCount > 0) {
                        throw new \RuntimeException('This dormitory has viewing request records. Keep it inactive instead of deleting historical data.');
                    }

                    $this->entityManager->remove($dormitory);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'Dormitory deleted successfully.');
                } elseif (in_array($action, ['create', 'update'], true)) {
                    $name = trim((string) $request->request->get('name', ''));
                    $location = trim((string) $request->request->get('location', ''));
                    $address = trim((string) $request->request->get('address', ''));
                    $description = trim((string) $request->request->get('description', ''));
                    $status = (string) $request->request->get('status', 'active');

                    if ($name === '' || $location === '' || !in_array($status, self::DORMITORY_STATUSES, true)) {
                        throw new \RuntimeException('Name, location, and a valid status are required.');
                    }

                    $dormitory = null;
                    if ($action === 'update') {
                        if (!$id) {
                            throw new \RuntimeException('Dormitory ID is missing.');
                        }
                        $dormitory = $this->entityManager->getRepository(Dormitory::class)->find($id);
                        if (!$dormitory) {
                            throw new \RuntimeException('Dormitory not found.');
                        }
                    } else {
                        $dormitory = new Dormitory();
                        $dormitory->setCreatedAt(new \DateTimeImmutable());
                        $this->entityManager->persist($dormitory);
                    }

                    $dormitory->setName($name)
                        ->setLocation($location)
                        ->setAddress($address !== '' ? $address : null)
                        ->setDescription($description !== '' ? $description : null)
                        ->setStatus($status);

                    $this->entityManager->flush();
                    $this->addFlash('success', $action === 'create' ? 'Dormitory added successfully.' : 'Dormitory updated successfully.');
                } else {
                    throw new \RuntimeException('Invalid dormitory action.');
                }
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'That dormitory conflicts with an existing database record.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('admin_dormitories');
        }

        $editId = filter_var($request->query->get('edit'), FILTER_VALIDATE_INT) ?: 0;
        $editDormitory = $editId ? $this->entityManager->getRepository(Dormitory::class)->find($editId) : null;

        if ($editId && !$editDormitory) {
            $this->addFlash('error', 'Dormitory not found.');
        }

        $dormitories = $this->entityManager
            ->getRepository(Dormitory::class)
            ->findBy([], ['location' => 'ASC', 'name' => 'ASC']);

        $rows = array_map(static function (Dormitory $dormitory): array {
            return [
                'id' => $dormitory->getId(),
                'name' => $dormitory->getName(),
                'location' => $dormitory->getLocation(),
                'address' => $dormitory->getAddress(),
                'description' => $dormitory->getDescription(),
                'status' => $dormitory->getStatus(),
            ];
        }, $dormitories);

        return $this->render('admin/dormitories.html.twig', [
            'title' => 'Dormitories',
            'statuses' => self::DORMITORY_STATUSES,
            'rows' => $rows,
            'edit_dormitory' => $editDormitory,
            'csrf_token' => $this->csrfTokenManager->getToken('dormitory_action')->getValue(),
        ]);
    }

    #[Route('/admin/users', name: 'admin_users', methods: ['GET', 'POST'])]
    public function users(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->validCsrf('user_action', $request)) {
                return $this->redirectWithError('admin_users', 'The form security token is invalid. Please try again.');
            }

            $action = (string) $request->request->get('action', '');
            $id = filter_var($request->request->get('id'), FILTER_VALIDATE_INT);

            try {
                if ($action === 'delete') {
                    if (!$id) {
                        throw new \RuntimeException('User ID is missing.');
                    }

                    /** @var User|null $user */
                    $user = $this->entityManager->getRepository(User::class)->find($id);
                    if (!$user) {
                        throw new \RuntimeException('User not found.');
                    }

                    if ($user->getRole() === 'admin') {
                        throw new \RuntimeException('Administrator accounts are protected from deletion.');
                    }

                    $viewingCount = $this->entityManager->getRepository(ViewingRequest::class)->count(['user' => $user]);
                    $applicationCount = $this->entityManager->getRepository(RoomApplication::class)->count(['user' => $user]);

                    if ($viewingCount > 0 || $applicationCount > 0) {
                        throw new \RuntimeException('This user has related records. Set the account to inactive or suspended instead of deleting it.');
                    }

                    $this->entityManager->remove($user);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'User deleted successfully.');
                } elseif (in_array($action, ['create', 'update'], true)) {
                    $username = trim((string) $request->request->get('username', ''));
                    $email = trim((string) $request->request->get('email', ''));
                    $firstName = trim((string) $request->request->get('first_name', ''));
                    $lastName = trim((string) $request->request->get('last_name', ''));
                    $phone = trim((string) $request->request->get('phone', ''));
                    $password = (string) $request->request->get('password', '');
                    $role = (string) $request->request->get('role', 'applicant');
                    $accountStatus = (string) $request->request->get('account_status', 'active');

                    if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $firstName === '' || $lastName === '') {
                        throw new \RuntimeException('Please provide valid username, email, first name, and last name values.');
                    }

                    if (!in_array($role, self::USER_ROLES, true) || !in_array($accountStatus, self::ACCOUNT_STATUSES, true)) {
                        throw new \RuntimeException('Please choose a valid role and account status.');
                    }

                    $user = null;
                    if ($action === 'update') {
                        if (!$id) {
                            throw new \RuntimeException('User ID is missing.');
                        }
                        /** @var User|null $user */
                        $user = $this->entityManager->getRepository(User::class)->find($id);
                        if (!$user) {
                            throw new \RuntimeException('User not found.');
                        }
                        if ($user->getRole() === 'admin' && $role !== 'admin') {
                            $adminCount = $this->entityManager->getRepository(User::class)->count(['role' => 'admin']);
                            if ($adminCount <= 1) {
                                throw new \RuntimeException('The last administrator account cannot be changed to a non-admin role.');
                            }
                        }
                    } else {
                        if (mb_strlen($password) < 8) {
                            throw new \RuntimeException('Password must be at least 8 characters.');
                        }
                        $user = new User();
                        $user->setCreatedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());
                        $this->entityManager->persist($user);
                    }

                    $duplicate = $this->entityManager
                        ->getRepository(User::class)
                        ->createQueryBuilder('u')
                        ->where('(u.username = :username OR u.email = :email)')
                        ->andWhere('u.id != :id')
                        ->setParameter('username', $username)
                        ->setParameter('email', $email)
                        ->setParameter('id', $user->getId() ?? 0)
                        ->getQuery()
                        ->getResult();

                    if ($duplicate !== []) {
                        throw new \RuntimeException('Username or email is already in use by another user.');
                    }

                    $user->setUsername($username)
                        ->setEmail($email)
                        ->setFirstName($firstName)
                        ->setLastName($lastName)
                        ->setPhone($phone !== '' ? $phone : null)
                        ->setRole($role)
                        ->setAccountStatus($accountStatus);

                    if ($password !== '') {
                        if (mb_strlen($password) < 8) {
                            throw new \RuntimeException('New password must be at least 8 characters.');
                        }
                        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
                    }

                    $this->entityManager->flush();
                    $this->addFlash('success', $action === 'create' ? 'User created successfully.' : 'User updated successfully.');
                } else {
                    throw new \RuntimeException('Invalid user action.');
                }
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Username or email is already in use.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('admin_users');
        }

        $editId = filter_var($request->query->get('edit'), FILTER_VALIDATE_INT) ?: 0;
        $editUser = $editId ? $this->entityManager->getRepository(User::class)->find($editId) : null;

        if ($editId && !$editUser) {
            $this->addFlash('error', 'User not found.');
        }

        $users = $this->entityManager->getRepository(User::class)->findBy([], ['createdAt' => 'DESC']);
        $rows = array_map(static function (User $user): array {
            return [
                'id' => $user->getId(),
                'name' => $user->getFullName(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone() ?: 'No phone',
                'role' => $user->getRole(),
                'status' => $user->getAccountStatus(),
            ];
        }, $users);

        return $this->render('admin/users.html.twig', [
            'title' => 'Users',
            'roles' => self::USER_ROLES,
            'account_statuses' => self::ACCOUNT_STATUSES,
            'rows' => $rows,
            'edit_user' => $editUser,
            'csrf_token' => $this->csrfTokenManager->getToken('user_action')->getValue(),
        ]);
    }

    #[Route('/admin/viewing-requests', name: 'admin_viewing_requests', methods: ['GET', 'POST'])]
    public function viewingRequests(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->validCsrf('viewing_request_action', $request)) {
                return $this->redirectWithError('admin_viewing_requests', 'The form security token is invalid. Please try again.');
            }

            $action = (string) $request->request->get('action', '');
            $id = filter_var($request->request->get('id'), FILTER_VALIDATE_INT);

            try {
                if ($action === 'delete') {
                    if (!$id) {
                        throw new \RuntimeException('Viewing request ID is missing.');
                    }
                    $entity = $this->entityManager->getRepository(ViewingRequest::class)->find($id);
                    if (!$entity) {
                        throw new \RuntimeException('Viewing request not found.');
                    }
                    $this->entityManager->remove($entity);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'Viewing request deleted successfully.');
                } elseif (in_array($action, ['create', 'update'], true)) {
                    $user = $this->customerFromRequest($request->request->get('user_id'));
                    $dormitory = $this->activeDormitoryFromRequest($request->request->get('dormitory_id'));
                    $preferredDate = $this->parseDate((string) $request->request->get('preferred_date', ''), 'Preferred date');
                    $preferredTime = $this->parseTime((string) $request->request->get('preferred_time', ''), 'Preferred time');
                    $status = (string) $request->request->get('status', 'pending');
                    $notes = trim((string) $request->request->get('notes', ''));

                    if (!in_array($status, self::VIEWING_REQUEST_STATUSES, true)) {
                        throw new \RuntimeException('Please choose a valid viewing request status.');
                    }

                    if ($action === 'update') {
                        if (!$id) {
                            throw new \RuntimeException('Viewing request ID is missing.');
                        }
                        $entity = $this->entityManager->getRepository(ViewingRequest::class)->find($id);
                        if (!$entity) {
                            throw new \RuntimeException('Viewing request not found.');
                        }
                    } else {
                        $entity = new ViewingRequest();
                        $entity->setCreatedAt(new \DateTimeImmutable());
                        $this->entityManager->persist($entity);
                    }

                    $entity->setUser($user)
                        ->setDormitory($dormitory)
                        ->setPreferredDate($preferredDate)
                        ->setPreferredTime($preferredTime)
                        ->setStatus($status)
                        ->setNotes($notes !== '' ? $notes : null);

                    $this->entityManager->flush();
                    $this->addFlash('success', $action === 'create' ? 'Viewing request created successfully.' : 'Viewing request updated successfully.');
                } else {
                    throw new \RuntimeException('Invalid viewing request action.');
                }
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('admin_viewing_requests');
        }

        $editId = filter_var($request->query->get('edit'), FILTER_VALIDATE_INT) ?: 0;
        $editRequest = $editId ? $this->entityManager->getRepository(ViewingRequest::class)->find($editId) : null;
        if ($editId && !$editRequest) {
            $this->addFlash('error', 'Viewing request not found.');
        }

        $customers = $this->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.role IN (:roles)')
            ->setParameter('roles', ['applicant', 'resident'])
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()->getResult();

        $activeDormitories = $this->entityManager
            ->getRepository(Dormitory::class)
            ->findBy(['status' => 'active'], ['location' => 'ASC', 'name' => 'ASC']);

        $entities = $this->entityManager->getRepository(ViewingRequest::class)->findAll();
        usort($entities, static function (ViewingRequest $a, ViewingRequest $b): int {
            $dateCompare = ($b->getPreferredDate() <=> $a->getPreferredDate());
            if ($dateCompare !== 0) {
                return $dateCompare;
            }
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        $rows = array_map(static function (ViewingRequest $entity): array {
            $user = $entity->getUser();
            $dormitory = $entity->getDormitory();
            return [
                'id' => $entity->getId(),
                'customer' => $user?->getFullName() ?: 'Unknown User',
                'email' => $user?->getEmail() ?: '—',
                'location' => $dormitory?->getLocation() ?: '—',
                'dormitory' => $dormitory?->getName() ?: '—',
                'date' => $entity->getPreferredDate()?->format('Y-m-d') ?: '—',
                'time' => $entity->getPreferredTime()?->format('H:i') ?: '—',
                'notes' => $entity->getNotes(),
                'status' => $entity->getStatus(),
            ];
        }, $entities);

        $users = array_map(static fn (User $user): array => [
            'id' => $user->getId(), 'name' => $user->getFullName(), 'email' => $user->getEmail(),
        ], $customers);

        $dorms = array_map(static fn (Dormitory $dormitory): array => [
            'id' => $dormitory->getId(), 'label' => $dormitory->getLocation() . ' — ' . $dormitory->getName(),
        ], $activeDormitories);

        return $this->render('admin/viewing_requests.html.twig', [
            'title' => 'Viewing Requests',
            'users' => $users,
            'dorms' => $dorms,
            'statuses' => self::VIEWING_REQUEST_STATUSES,
            'rows' => $rows,
            'edit_request' => $editRequest,
            'csrf_token' => $this->csrfTokenManager->getToken('viewing_request_action')->getValue(),
        ]);
    }

    #[Route('/admin/room-applications', name: 'admin_room_applications', methods: ['GET', 'POST'])]
    public function roomApplications(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->validCsrf('room_application_action', $request)) {
                return $this->redirectWithError('admin_room_applications', 'The form security token is invalid. Please try again.');
            }

            $action = (string) $request->request->get('action', '');
            $id = filter_var($request->request->get('id'), FILTER_VALIDATE_INT);
            $connection = $this->entityManager->getConnection();

            try {
                $connection->beginTransaction();

                if ($action === 'move_out') {
                    if (!$id) {
                        throw new \RuntimeException('Application ID is missing.');
                    }
                    /** @var RoomApplication|null $application */
                    $application = $this->entityManager->getRepository(RoomApplication::class)->find($id);
                    if (!$application) {
                        throw new \RuntimeException('Application not found.');
                    }
                    if ($application->getStatus() !== 'approved') {
                        throw new \RuntimeException('Only an approved tenant can be marked as moved out.');
                    }

                    $moveOut = $this->parseDate((string) $request->request->get('move_out_date', ''), 'Move-out date');
                    if ($application->getMoveInDate() && $moveOut < $application->getMoveInDate()) {
                        throw new \RuntimeException('Move-out date cannot be earlier than the move-in date.');
                    }

                    $this->restoreRoomSlot($application->getRoom());
                    $application->setStatus('moved_out')->setMoveOutDate($moveOut);
                    $this->entityManager->flush();
                    $this->syncResidentRole($application->getUser());
                    $this->entityManager->flush();
                    $connection->commit();
                    $this->addFlash('success', 'Tenant marked as moved out successfully.');
                } elseif ($action === 'delete') {
                    if (!$id) {
                        throw new \RuntimeException('Application ID is missing.');
                    }
                    /** @var RoomApplication|null $application */
                    $application = $this->entityManager->getRepository(RoomApplication::class)->find($id);
                    if (!$application) {
                        throw new \RuntimeException('Application not found.');
                    }

                    $user = $application->getUser();
                    $room = $application->getRoom();
                    if ($application->getStatus() === 'approved') {
                        $this->restoreRoomSlot($room);
                    }

                    $this->entityManager->remove($application);
                    $this->entityManager->flush();
                    $this->syncResidentRole($user);
                    $this->entityManager->flush();
                    $connection->commit();
                    $this->addFlash('success', 'Room application deleted successfully.');
                } elseif (in_array($action, ['create', 'update'], true)) {
                    $user = $this->customerFromRequest($request->request->get('user_id'));
                    $room = $this->activeRoomFromRequest($request->request->get('room_id'));
                    $moveInValue = trim((string) $request->request->get('move_in_date', ''));
                    $moveIn = $moveInValue === '' ? null : $this->parseDate($moveInValue, 'Move-in date');
                    $status = (string) $request->request->get('status', 'pending');
                    $notes = trim((string) $request->request->get('notes', ''));

                    if (!in_array($status, self::ROOM_APPLICATION_STATUSES, true)) {
                        throw new \RuntimeException('Please choose a valid room application status.');
                    }
                    if ($status === 'moved_out') {
                        throw new \RuntimeException('Use the Move Out action to create a moved-out record.');
                    }

                    if ($action === 'create') {
                        $application = new RoomApplication();
                        $application->setCreatedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());
                        $this->entityManager->persist($application);

                        if ($status === 'approved') {
                            $this->consumeRoomSlot($room);
                        }
                    } else {
                        if (!$id) {
                            throw new \RuntimeException('Application ID is missing.');
                        }
                        /** @var RoomApplication|null $application */
                        $application = $this->entityManager->getRepository(RoomApplication::class)->find($id);
                        if (!$application) {
                            throw new \RuntimeException('Application not found.');
                        }
                        if ($application->getStatus() === 'moved_out') {
                            throw new \RuntimeException('Moved-out records are historical records and cannot be edited.');
                        }

                        $oldRoom = $application->getRoom();
                        $oldUser = $application->getUser();
                        $oldApproved = $application->getStatus() === 'approved';
                        $newApproved = $status === 'approved';
                        $sameRoom = $oldRoom?->getId() === $room->getId();

                        if ($oldApproved && (!$newApproved || !$sameRoom)) {
                            $this->restoreRoomSlot($oldRoom);
                        }
                        if ($newApproved && (!$oldApproved || !$sameRoom)) {
                            $this->consumeRoomSlot($room);
                        }
                    }

                    $application->setUser($user)
                        ->setRoom($room)
                        ->setMoveInDate($moveIn)
                        ->setStatus($status)
                        ->setMoveOutDate(null)
                        ->setNotes($notes !== '' ? $notes : null);

                    $this->entityManager->flush();
                    $this->syncResidentRole($user);
                    if (isset($oldUser) && $oldUser && $oldUser->getId() !== $user->getId()) {
                        $this->syncResidentRole($oldUser);
                    }
                    $this->entityManager->flush();
                    $connection->commit();
                    $this->addFlash('success', $action === 'create' ? 'Room application created successfully.' : 'Room application updated successfully.');
                } else {
                    throw new \RuntimeException('Invalid room application action.');
                }
            } catch (\Throwable $e) {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('admin_room_applications');
        }

        $editId = filter_var($request->query->get('edit'), FILTER_VALIDATE_INT) ?: 0;
        $editApplication = $editId ? $this->entityManager->getRepository(RoomApplication::class)->find($editId) : null;
        if ($editId && !$editApplication) {
            $this->addFlash('error', 'Room application not found.');
        }

        $customers = $this->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.role IN (:roles)')
            ->setParameter('roles', ['applicant', 'resident'])
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()->getResult();

        $activeRooms = $this->entityManager
            ->getRepository(Room::class)
            ->createQueryBuilder('r')
            ->join('r.dormitory', 'd')
            ->addSelect('d')
            ->where('d.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('d.location', 'ASC')
            ->addOrderBy('r.roomNumber', 'ASC')
            ->getQuery()->getResult();

        $entities = $this->entityManager->getRepository(RoomApplication::class)->findAll();
        $statusOrder = array_flip(self::ROOM_APPLICATION_STATUSES);
        usort($entities, static function (RoomApplication $a, RoomApplication $b) use ($statusOrder): int {
            $statusComparison = ($statusOrder[$a->getStatus()] ?? PHP_INT_MAX) <=> ($statusOrder[$b->getStatus()] ?? PHP_INT_MAX);
            return $statusComparison !== 0 ? $statusComparison : ($b->getCreatedAt() <=> $a->getCreatedAt());
        });

        $rows = array_map(static function (RoomApplication $entity): array {
            $user = $entity->getUser();
            $room = $entity->getRoom();
            $dormitory = $room?->getDormitory();
            return [
                'id' => $entity->getId(),
                'customer' => $user?->getFullName() ?: 'Unknown User',
                'email' => $user?->getEmail() ?: '—',
                'location' => $dormitory?->getLocation() ?: '—',
                'room' => $room?->getRoomNumber() ?: '—',
                'room_type' => self::ROOM_TYPES[$room?->getRoomType() ?? ''] ?? ($room?->getRoomType() ?: '—'),
                'move_in' => $entity->getMoveInDate()?->format('Y-m-d') ?: '—',
                'move_out' => $entity->getMoveOutDate()?->format('Y-m-d') ?: '—',
                'notes' => $entity->getNotes(),
                'status' => $entity->getStatus(),
            ];
        }, $entities);

        $users = array_map(static fn (User $user): array => [
            'id' => $user->getId(), 'name' => $user->getFullName(), 'email' => $user->getEmail(),
        ], $customers);

        $rooms = array_map(static function (Room $room): array {
            $dormitory = $room->getDormitory();
            return [
                'id' => $room->getId(),
                'label' => sprintf('%s / %s — %s — %d slots', $dormitory?->getLocation() ?: 'Unknown', $room->getRoomNumber() ?: '—', self::ROOM_TYPES[$room->getRoomType()] ?? $room->getRoomType(), $room->getAvailableSlots()),
            ];
        }, $activeRooms);

        return $this->render('admin/room_applications.html.twig', [
            'title' => 'Room Applications',
            'users' => $users,
            'rooms' => $rooms,
            'statuses' => self::ROOM_APPLICATION_STATUSES,
            'rows' => $rows,
            'edit_application' => $editApplication,
            'csrf_token' => $this->csrfTokenManager->getToken('room_application_action')->getValue(),
        ]);
    }

    #[Route('/admin/rooms', name: 'admin_rooms', methods: ['GET', 'POST'])]
    public function rooms(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->validCsrf('room_action', $request)) {
                return $this->redirectWithError('admin_rooms', 'The form security token is invalid. Please try again.');
            }

            $action = (string) $request->request->get('action', '');
            $id = filter_var($request->request->get('id'), FILTER_VALIDATE_INT);

            try {
                if ($action === 'delete') {
                    if (!$id) {
                        throw new \RuntimeException('Room ID is missing.');
                    }
                    $room = $this->entityManager->getRepository(Room::class)->find($id);
                    if (!$room) {
                        throw new \RuntimeException('Room not found.');
                    }
                    $applicationCount = $this->entityManager->getRepository(RoomApplication::class)->count(['room' => $room]);
                    if ($applicationCount > 0) {
                        throw new \RuntimeException('This room has application records. Cancel/delete those applications first, or mark the room inactive instead.');
                    }
                    $this->entityManager->remove($room);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'Room deleted successfully.');
                } elseif (in_array($action, ['create', 'update'], true)) {
                    $dormitoryId = filter_var($request->request->get('dormitory_id'), FILTER_VALIDATE_INT);
                    $roomNumber = trim((string) $request->request->get('room_number', ''));
                    $roomType = (string) $request->request->get('room_type', '4_person');
                    $capacity = filter_var($request->request->get('capacity'), FILTER_VALIDATE_INT);
                    $availableSlots = filter_var($request->request->get('available_slots'), FILTER_VALIDATE_INT);
                    $monthlyRate = filter_var($request->request->get('monthly_rate'), FILTER_VALIDATE_FLOAT);
                    $status = (string) $request->request->get('status', 'available');

                    if (!$dormitoryId || $roomNumber === '' || !isset(self::ROOM_TYPES[$roomType]) || !in_array($status, self::ROOM_STATUSES, true)) {
                        throw new \RuntimeException('Please provide valid room details.');
                    }
                    if ($capacity === false || $capacity < 1) {
                        throw new \RuntimeException('Room capacity must be at least 1.');
                    }
                    if ($availableSlots === false || $availableSlots < 0 || $availableSlots > $capacity) {
                        throw new \RuntimeException('Available slots must be between 0 and the room capacity.');
                    }
                    if ($monthlyRate === false || $monthlyRate < 0) {
                        throw new \RuntimeException('Monthly rate cannot be negative.');
                    }
                    if ($status === 'available' && $availableSlots < 1) {
                        throw new \RuntimeException('An available room must have at least 1 available slot.');
                    }
                    if ($status === 'full' && $availableSlots !== 0) {
                        throw new \RuntimeException('A full room must have 0 available slots.');
                    }

                    $dormitory = $this->entityManager->getRepository(Dormitory::class)->find($dormitoryId);
                    if (!$dormitory || $dormitory->getStatus() !== 'active') {
                        throw new \RuntimeException('Please choose an active dormitory.');
                    }

                    if ($action === 'update') {
                        if (!$id) {
                            throw new \RuntimeException('Room ID is missing.');
                        }
                        $room = $this->entityManager->getRepository(Room::class)->find($id);
                        if (!$room) {
                            throw new \RuntimeException('Room not found.');
                        }
                        $currentOccupants = $room->getCapacity() - $room->getAvailableSlots();
                        if ($capacity < $currentOccupants) {
                            throw new \RuntimeException('Room capacity cannot be smaller than the number of current occupants.');
                        }
                    } else {
                        $room = new Room();
                        $room->setCreatedAt(new \DateTimeImmutable());
                        $this->entityManager->persist($room);
                    }

                    $room->setDormitory($dormitory)
                        ->setRoomNumber($roomNumber)
                        ->setRoomType($roomType)
                        ->setCapacity($capacity)
                        ->setMonthlyRate(number_format((float) $monthlyRate, 2, '.', ''))
                        ->setAvailableSlots($availableSlots)
                        ->setStatus($status);

                    $this->entityManager->flush();
                    $this->addFlash('success', $action === 'create' ? 'Room added successfully.' : 'Room updated successfully.');
                } else {
                    throw new \RuntimeException('Invalid room action.');
                }
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'That room number already exists in the selected dormitory.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('admin_rooms');
        }

        $editId = filter_var($request->query->get('edit'), FILTER_VALIDATE_INT) ?: 0;
        $editRoom = $editId ? $this->entityManager->getRepository(Room::class)->find($editId) : null;

        $activeDormitories = $this->entityManager->getRepository(Dormitory::class)->findBy(['status' => 'active'], ['location' => 'ASC', 'name' => 'ASC']);
        $rooms = $this->entityManager
            ->getRepository(Room::class)
            ->createQueryBuilder('r')
            ->join('r.dormitory', 'd')
            ->addSelect('d')
            ->orderBy('d.location', 'ASC')
            ->addOrderBy('r.roomNumber', 'ASC')
            ->getQuery()->getResult();

        $rows = array_map(static function (Room $room): array {
            $dormitory = $room->getDormitory();
            return [
                'id' => $room->getId(),
                'location' => $dormitory?->getLocation() ?: '—',
                'dormitory' => $dormitory?->getName() ?: '—',
                'room' => $room->getRoomNumber(),
                'type' => self::ROOM_TYPES[$room->getRoomType()] ?? $room->getRoomType(),
                'monthly_rate' => $room->getMonthlyRate(),
                'slots' => $room->getAvailableSlots() . ' / ' . $room->getCapacity(),
                'status' => $room->getStatus(),
            ];
        }, $rooms);

        return $this->render('admin/rooms.html.twig', [
            'title' => 'Rooms',
            'dorms' => array_map(static fn (Dormitory $dormitory): array => [
                'id' => $dormitory->getId(),
                'label' => $dormitory->getLocation() . ' — ' . $dormitory->getName(),
            ], $activeDormitories),
            'types' => self::ROOM_TYPES,
            'statuses' => self::ROOM_STATUSES,
            'rows' => $rows,
            'edit_room' => $editRoom,
            'csrf_token' => $this->csrfTokenManager->getToken('room_action')->getValue(),
        ]);
    }

    private function validCsrf(string $id, Request $request): bool
    {
        return $this->isCsrfTokenValid($id, (string) $request->request->get('_token'));
    }

    private function redirectWithError(string $route, string $message): Response
    {
        $this->addFlash('error', $message);
        return $this->redirectToRoute($route);
    }

    private function customerFromRequest(mixed $id): User
    {
        $userId = filter_var($id, FILTER_VALIDATE_INT);
        if (!$userId) {
            throw new \RuntimeException('Please choose a customer.');
        }
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user || !in_array($user->getRole(), ['applicant', 'resident'], true)) {
            throw new \RuntimeException('Please choose a valid applicant or resident.');
        }
        return $user;
    }

    private function activeDormitoryFromRequest(mixed $id): Dormitory
    {
        $dormitoryId = filter_var($id, FILTER_VALIDATE_INT);
        if (!$dormitoryId) {
            throw new \RuntimeException('Please choose a dormitory.');
        }
        $dormitory = $this->entityManager->getRepository(Dormitory::class)->find($dormitoryId);
        if (!$dormitory || $dormitory->getStatus() !== 'active') {
            throw new \RuntimeException('Please choose an active dormitory.');
        }
        return $dormitory;
    }

    private function activeRoomFromRequest(mixed $id): Room
    {
        $roomId = filter_var($id, FILTER_VALIDATE_INT);
        if (!$roomId) {
            throw new \RuntimeException('Please choose a room.');
        }
        $room = $this->entityManager->getRepository(Room::class)->find($roomId);
        if (!$room || !$room->getDormitory() || $room->getDormitory()->getStatus() !== 'active') {
            throw new \RuntimeException('Please choose a room in an active dormitory.');
        }
        return $room;
    }

    private function parseDate(string $value, string $label): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \RuntimeException($label . ' must be a valid date.');
        }
        return $date;
    }

    private function parseTime(string $value, string $label): \DateTimeImmutable
    {
        $time = \DateTimeImmutable::createFromFormat('!H:i', $value);
        if (!$time || $time->format('H:i') !== $value) {
            throw new \RuntimeException($label . ' must be a valid time.');
        }
        return $time;
    }

    private function consumeRoomSlot(Room $room): void
    {
        if ($room->getStatus() !== 'available' || $room->getAvailableSlots() < 1) {
            throw new \RuntimeException('The selected room is not available or has no available slots.');
        }

        $room->setAvailableSlots($room->getAvailableSlots() - 1);
        if ($room->getAvailableSlots() === 0) {
            $room->setStatus('full');
        }
    }

    private function restoreRoomSlot(?Room $room): void
    {
        if (!$room) {
            throw new \RuntimeException('The room associated with this application no longer exists.');
        }

        $slots = min($room->getCapacity(), $room->getAvailableSlots() + 1);
        $room->setAvailableSlots($slots);

        if (!in_array($room->getStatus(), ['maintenance', 'inactive'], true)) {
            $room->setStatus('available');
        }
    }

    private function syncResidentRole(?User $user): void
    {
        if (!$user || $user->getRole() === 'admin') {
            return;
        }

        $approvedCount = $this->entityManager
            ->getRepository(RoomApplication::class)
            ->count(['user' => $user, 'status' => 'approved']);

        $user->setRole($approvedCount > 0 ? 'resident' : 'applicant');
    }
}
