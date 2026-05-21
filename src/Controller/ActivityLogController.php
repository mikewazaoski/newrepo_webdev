<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    #[Route('/', name: 'app_activity_log_index', methods: ['GET'])]
    public function index(
        Request $request,
        ActivityLogRepository $logRepository,
        UserRepository $userRepository
    ): Response {
        // Safely get user ID - handle empty/invalid values
        $userIdParam = $request->query->get('user', '');
        $userId = ($userIdParam !== '' && is_numeric($userIdParam)) ? (int)$userIdParam : 0;
        
        $action = $request->query->get('action', '');
        $startDateParam = $request->query->get('start_date', '');
        $endDateParam = $request->query->get('end_date', '');
        
        $startDate = ($startDateParam !== '') ? new \DateTime($startDateParam) : null;
        $endDate = ($endDateParam !== '') ? new \DateTime($endDateParam) : null;

        $user = ($userId > 0) ? $userRepository->find($userId) : null;

        $logs = $logRepository->findByFilters($user, $action, $startDate, $endDate);
        $users = $userRepository->findAll();

        // Process logs to extract target data
        $processedLogs = [];
        foreach ($logs as $log) {
            $targetData = $log->getEntityType();
            if ($log->getEntityId()) {
                $targetData .= ' (ID: ' . $log->getEntityId() . ')';
            }
            
            // Try to extract target from affectedData
            $affectedData = $log->getAffectedData();
            if ($affectedData) {
                $decoded = json_decode($affectedData, true);
                if ($decoded && isset($decoded['target'])) {
                    $targetData = $decoded['target'];
                } elseif ($decoded && isset($decoded['name'])) {
                    $targetData = $log->getEntityType() . ': ' . $decoded['name'] . ($log->getEntityId() ? ' (ID: ' . $log->getEntityId() . ')' : '');
                }
            }
            
            $processedLogs[] = [
                'log' => $log,
                'targetData' => $targetData,
            ];
        }

        return $this->render('activity_log/index.html.twig', [
            'logs' => $processedLogs,
            'users' => $users,
            'selected_user' => $user,
            'selected_action' => $action,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    #[Route('/{id}', name: 'app_activity_log_show', methods: ['GET'])]
    public function show(int $id, ActivityLogRepository $logRepository): Response
    {
        $log = $logRepository->find($id);

        if (!$log) {
            throw $this->createNotFoundException('Log not found');
        }

        return $this->render('activity_log/show.html.twig', [
            'log' => $log,
        ]);
    }
}

