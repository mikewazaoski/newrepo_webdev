<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack
    ) {
    }

    public function log(
        ?User $user,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $description = null,
        ?array $affectedData = null
    ): void {
        $log = new ActivityLog();
        $log->setUser($user);
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setDescription($description);

        if ($affectedData !== null) {
            $log->setAffectedData(json_encode($affectedData, JSON_PRETTY_PRINT));
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $log->setIpAddress($request->getClientIp());
        }

        $this->em->persist($log);
        $this->em->flush();
    }

    public function logLogin(User $user): void
    {
        $targetData = "User: {$user->getUsername()} (ID: {$user->getId()})";
        $this->log($user, 'LOGIN', 'User', $user->getId(), "User {$user->getUsername()} logged in", ['target' => $targetData]);
    }

    public function logLogout(User $user): void
    {
        $targetData = "User: {$user->getUsername()} (ID: {$user->getId()})";
        $this->log($user, 'LOGOUT', 'User', $user->getId(), "User {$user->getUsername()} logged out", ['target' => $targetData]);
    }

    public function logCreate(User $user, string $entityType, int $entityId, ?array $data = null): void
    {
        $targetData = $this->formatTargetData($entityType, $entityId, $data);
        $sanitizedData = $this->sanitizeDataForLogging($data);
        $this->log($user, 'CREATE', $entityType, $entityId, "Created {$entityType}", ['target' => $targetData, 'details' => $sanitizedData]);
    }

    public function logUpdate(User $user, string $entityType, int $entityId, ?array $data = null): void
    {
        $targetData = $this->formatTargetData($entityType, $entityId, $data);
        $sanitizedData = $this->sanitizeDataForLogging($data);
        $this->log($user, 'UPDATE', $entityType, $entityId, "Updated {$entityType}", ['target' => $targetData, 'details' => $sanitizedData]);
    }

    public function logDelete(User $user, string $entityType, int $entityId, ?array $data = null): void
    {
        $targetData = $this->formatTargetData($entityType, $entityId, $data);
        $sanitizedData = $this->sanitizeDataForLogging($data);
        $this->log($user, 'DELETE', $entityType, $entityId, "Deleted {$entityType}", ['target' => $targetData, 'details' => $sanitizedData]);
    }

    /**
     * Sanitize data for logging - convert arrays and objects to strings
     */
    private function sanitizeDataForLogging(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Convert array to JSON string
                $sanitized[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_object($value)) {
                // Convert object to string representation
                $sanitized[$key] = method_exists($value, '__toString') ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $sanitized[$key] = 'null';
            } else {
                // Convert to string for safety
                $sanitized[$key] = (string) $value;
            }
        }

        return $sanitized;
    }

    public function logStockChange(User $user, Product $product, int $oldStock, int $newStock, ?string $additionalInfo = null): void
    {
        $change = $newStock - $oldStock;
        $changeType = $change > 0 ? 'STOCK_INCREASE' : ($change < 0 ? 'STOCK_DECREASE' : 'STOCK_UPDATE');
        
        $description = "Stock changed from {$oldStock} to {$newStock} for product '{$product->getName()}'";
        if ($additionalInfo) {
            $description .= " - {$additionalInfo}";
        }
        
        $this->log(
            $user,
            $changeType,
            'Product',
            $product->getId(),
            $description,
            [
                'productName' => $product->getName(),
                'productId' => $product->getId(),
                'oldStock' => $oldStock,
                'newStock' => $newStock,
                'change' => $change,
                'additionalInfo' => $additionalInfo
            ]
        );
    }

    private function formatTargetData(string $entityType, int $entityId, ?array $data = null): string
    {
        // Format: "EntityType: Name (ID: X)" or "EntityType: ID: X"
        $name = null;
        if ($data && is_array($data)) {
            // Try to find a name field - ensure we don't access arrays as strings
            foreach (['name', 'username', 'productName', 'customerName'] as $field) {
                if (isset($data[$field])) {
                    $value = $data[$field];
                    // Only use if it's a string or numeric, not an array
                    if (is_string($value) || is_numeric($value)) {
                        $name = (string) $value;
                        break;
                    }
                }
            }
        }
        
        if ($name) {
            return "{$entityType}: {$name} (ID: {$entityId})";
        }
        
        return "{$entityType} (ID: {$entityId})";
    }
}

