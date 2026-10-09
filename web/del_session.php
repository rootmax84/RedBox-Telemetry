<?php
require_once __DIR__ . '/src/helpers.php';

$deletesession = filter_input(INPUT_POST, 'deletesession', FILTER_SANITIZE_NUMBER_INT)
               ?? filter_input(INPUT_GET, 'deletesession', FILTER_SANITIZE_NUMBER_INT);

$cut_start = filter_input(INPUT_GET, 'cutstart', FILTER_SANITIZE_NUMBER_INT);
$cut_end   = filter_input(INPUT_GET, 'cutend',   FILTER_SANITIZE_NUMBER_INT);

if ($deletesession !== '' && $deletesession !== false && $deletesession !== null) {

    /* ────────────────────────────────────────────────────────
     * Частичное удаление: только диапазон внутри сессии.
     * ──────────────────────────────────────────────────────── */
    if ($cut_start !== null && $cut_start !== false && $cut_start !== '' &&
        $cut_end   !== null && $cut_end   !== false && $cut_end   !== '') {

        $db->begin_transaction();

        try {
            // 1. Удаляем точки в указанном диапазоне
            $db->execute_query(
                "DELETE FROM logs
                  WHERE user_id = ? AND session = ? AND time BETWEEN ? AND ?",
                [current_user_id(), $deletesession, $cut_start, $cut_end]
            );

            // 2. Пересчитываем статистику по остатку
            $stats_result = $db->execute_query(
                "SELECT
                    COUNT(*)  AS count,
                    MIN(time) AS session_start,
                    MAX(time) AS session_end
                 FROM logs
                 WHERE user_id = ? AND session = ?",
                [current_user_id(), $deletesession]
            );
            $stats = $stats_result->fetch_assoc();

            $new_size  = (int)($stats['count'] ?? 0);
            $new_start = $stats['session_start'] ?? null;
            $new_end   = $stats['session_end']   ?? null;

            // 3. Если вырезали всё — удаляем сессию целиком
            if ($new_size === 0 || $new_start === null || $new_end === null) {
                $db->execute_query(
                    "DELETE FROM sessions WHERE user_id = ? AND session = ?",
                    [current_user_id(), $deletesession]
                );
            } else {
                // 4. Иначе обновляем метаданные сессии
                $db->execute_query(
                    "UPDATE sessions SET sessionsize = ?, time = ?, timeend = ?
                      WHERE user_id = ? AND session = ?",
                    [$new_size, $new_start, $new_end, current_user_id(), $deletesession]
                );
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        cache_flush();

        // Если сессия исчезла — на главную, иначе — обратно в неё
        if ($new_size === 0) {
            header("Location: /");
        } else {
            header("Location: /?id=" . $deletesession);
        }
        exit;

    /* ────────────────────────────────────────────────────────
     * Полное удаление сессии.
     * ──────────────────────────────────────────────────────── */
    } else {

        $db->begin_transaction();

        try {
            $db->execute_query(
                "DELETE FROM logs WHERE user_id = ? AND session = ?",
                [current_user_id(), $deletesession]
            );

            $db->execute_query(
                "DELETE FROM sessions WHERE user_id = ? AND session = ?",
                [current_user_id(), $deletesession]
            );

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }

        cache_flush();
        header("Location: /?.");
        exit;
    }
}
