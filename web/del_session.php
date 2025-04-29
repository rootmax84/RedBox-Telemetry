<?php
$deletesession = filter_input(INPUT_POST, 'deletesession', FILTER_SANITIZE_NUMBER_INT) 
               ?? filter_input(INPUT_GET, 'deletesession', FILTER_SANITIZE_NUMBER_INT);

$cut_start = filter_input(INPUT_GET, 'cutstart', FILTER_SANITIZE_NUMBER_INT);
$cut_end = filter_input(INPUT_GET, 'cutend', FILTER_SANITIZE_NUMBER_INT);

if ($deletesession !== '' && $deletesession !== false && $deletesession !== null) {
    // Check if we need to delete only a part of the session or the entire session
    if ($cut_start !== null && $cut_start !== false && $cut_start !== '' && 
        $cut_end !== null && $cut_end !== false && $cut_end !== '') {
        // Delete only part of the session within the specified time range
        $db->execute_query(
            "DELETE FROM $db_table WHERE session=? AND time BETWEEN ? AND ?", 
            [$deletesession, $cut_start, $cut_end]
        );

        // Get new values for sessionsize, time (start) and timeend (end)
        $stats_query = "SELECT 
                          COUNT(*) as count,
                          MIN(time) as session_start,
                          MAX(time) as session_end
                        FROM $db_table 
                        WHERE session=?";

        $stats_result = $db->execute_query($stats_query, [$deletesession]);
        $stats = $stats_result->fetch_assoc();

        $new_size = $stats['count'];
        $new_start = $stats['session_start'];
        $new_end = $stats['session_end'];

        // Update session size and timestamps in the sessions table
        $db->execute_query(
            "UPDATE $db_sessions_table SET sessionsize=?, time=?, timeend=? WHERE session=?",
            [$new_size, $new_start, $new_end, $deletesession]
        );

        invalidateCache();
        header("Location: .?id=" . $deletesession);
        exit;
    } else {
        // Delete the entire session
        $db->execute_query("DELETE FROM $db_table WHERE session=?", [$deletesession]);
        $db->execute_query("DELETE FROM $db_sessions_table WHERE session=?", [$deletesession]);
        invalidateCache();
        header("Location: .");
        exit;
    }
}
