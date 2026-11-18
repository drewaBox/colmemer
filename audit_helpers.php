<?php
declare(strict_types=1);

function record_audit_event(
    PDO $db,
    int $actorId,
    string $actorName,
    string $action,
    string $entityType,
    ?string $entityId,
    string $summary,
    array $details = []
): void {
    $detailsJson = $details === []
        ? null
        : json_encode($details, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    $statement = $db->prepare(
        'INSERT INTO audit_events
            (actor_user_id, actor_name, action, entity_type, entity_id, summary, details_json)
         VALUES (:actor_user_id, :actor_name, :action, :entity_type, :entity_id, :summary, :details_json)'
    );
    $statement->execute([
        'actor_user_id' => $actorId > 0 ? $actorId : null,
        'actor_name' => mb_substr($actorName, 0, 150),
        'action' => mb_substr($action, 0, 64),
        'entity_type' => mb_substr($entityType, 0, 64),
        'entity_id' => $entityId !== null ? mb_substr($entityId, 0, 64) : null,
        'summary' => mb_substr($summary, 0, 500),
        'details_json' => $detailsJson,
    ]);
}
