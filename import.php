<?php

// Usage
// import.php [json-file] [db-file]
// Will default to "entries.json" and "data.db" for convenience

$json_file = $argv[1] ?? "entries.json";
$db_file = $argv[2] ?? "data.db";

$title_cache = [];
$force_update = false;
$db = new SQLite3($db_file);
$sql = array('insert_chunk' => $db->prepare('INSERT INTO chunk (uuid,entry_id,title_id,content) VALUES (:uuid,:entry_id,:title_id,:content)'),
             'update_chunk' => $db->prepare('UPDATE chunk SET entry_id = :entry_id, title_id = :title_id, content = :content WHERE uuid = :uuid'));

$input = get_input($json_file);

$entries = $input['journals'][0]['entries'];
foreach ($entries as $entry) {
    $entry_record = get_entry_record($entry);
    foreach ($entry['grids'] as $grid) {
        insert_chunk($entry_record['id'], $grid);
    }
}

function get_title_id($title) {
    global $db, $title_cache;
    $hash = md5($title);
    if (!array_key_exists($hash, $title_cache)) {
        $title_id = $db->querySingle("SELECT id FROM title WHERE title = '{$title}'");
        if (!$title_id) {
            $db->querySingle("INSERT INTO title (title) VALUES ('{$title}')");
            $title_id = $db->lastInsertRowID();
        }
        $title_cache[$hash] = $title_id;
    }
    return $title_cache[$hash];
}

function get_entry_record($entry) {
    global $db;

    $slot = $entry['slot'];
    $date = $slot['year']."-".str_pad($slot['month'], 2, "0", STR_PAD_LEFT)."-".str_pad($slot['day'], 2, "0", STR_PAD_LEFT);
    $entry_uuid = $entry['uuid'];

    $record = $db->querySingle("SELECT * FROM entry WHERE uuid = '{$entry_uuid}'", true);
    if (!$record) {
        $db->querySingle("INSERT INTO entry (uuid,date) VALUES ('{$entry_uuid}','{$date}')");
        $record = $db->querySingle("SELECT * FROM entry WHERE uuid = '{$entry_uuid}'", true);
    }

    echo "processed ".$date."\n";
    return $record;
}

function insert_chunk($entry_id, $chunk) {
    global $db, $sql, $force_update;

    $chunk_uuid = $chunk['uuid'];
    $title_id = get_title_id($chunk['title']);
    $content = $chunk['content'];

    $record = $db->querySingle("SELECT * FROM chunk WHERE uuid = '{$chunk_uuid}'", true);
    if (!$record) {
        $statement = $sql['insert_chunk'];
    } else if ($force_update) {
        $statement = $sql['update_chunk'];
    }

    if (isset($statement)) {
        //uuid,entry_id,title_id,content
        $statement->bindValue(':uuid', $chunk_uuid, SQLITE3_TEXT);
        $statement->bindValue(':entry_id', $entry_id, SQLITE3_INTEGER);
        $statement->bindValue(':title_id', $title_id, SQLITE3_INTEGER);
        $statement->bindValue(':content', $content, SQLITE3_TEXT);
        $statement->execute();
    }
}

function get_input($fn)
{
    $parsed_input = "";
    $raw_input = file_get_contents($fn);

    if (false) {
        $START = "GridDiary.json{";
        $END = "PK" . chr(1) . chr(2);
        $start_pos = strpos($raw_input, $START, 0) + (strlen($START) - 1);
        $end_pos = strpos($raw_input, $END, $start_pos);
        $trimmed_input = substr($raw_input, $start_pos, ($end_pos - $start_pos));
        die($trimmed_input);
    } else {
        $trimmed_input = $raw_input;
    }

    $decode = false;
    if ($decode) {
        $converted_input = mb_convert_encoding($trimmed_input, "Windows-1252", "UTF-8");
        $parsed_input = json_decode($converted_input, TRUE);
    } else {
        $parsed_input = json_decode($trimmed_input, TRUE);
    }
    if ($parsed_input == null) { die(json_last_error_msg()); }

    return $parsed_input;
}
