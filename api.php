<?php
$host = 'localhost';
$db = 'tabletop';
$username = 'root';
$password = 'kXPWLuyodFLt9m';

class TabletopAPI
{
    private $pdo;

    public function __construct($host, $dbname, $username, $password)
    {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function getPersonByNameIP($name)
    {
        $ip = '';
        if (getenv('HTTP_X_FORWARDED_FOR')) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $stmt = $this->pdo->prepare("SELECT * FROM person WHERE name = :name AND ip = :ip");
        $stmt->execute(['name' => $name, 'ip' => $ip]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    function getAttendancePGW($person_id, $game_id, $weekNum, $year)
    {
        $stmt = $this->pdo->prepare("SELECT monday, tuesday, wednesday, thursday, friday, saturday, sunday FROM attendance WHERE person_id = ? AND game_id = ? AND week_id = (SELECT id FROM week WHERE year = ? AND weekNum = ?)");
        $stmt->execute([$person_id, $game_id, $year, $weekNum]);

        $week = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$week) {
            return array(0, 0, 0, 0, 0, 0, 0);
        }
        return array(
            $week['monday'],
            $week['tuesday'],
            $week['wednesday'],
            $week['thursday'],
            $week['friday'],
            $week['saturday'],
            $week['sunday']
        );
    }

    public function upsertUser($name)
    {
        $ip = '';
        if (getenv('HTTP_X_FORWARDED_FOR')) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $stmt = $this->pdo->prepare("INSERT INTO person (name, useragent) VALUES (:name, :useragent) ON DUPLICATE KEY UPDATE name = :name");
        $stmt->execute(['name' => $name, 'ip' => $ip]);
    }

    public function getAttendanceForGameWeek($game_id, $weekNum, $year)
    {
        $query = "SELECT a.*, p.name
                  FROM attendance a
                  INNER JOIN person p ON a.person_id = p.id
                  INNER JOIN week w ON a.week_id = w.id
                  WHERE a.game_id = ?
                  AND w.weekNum = ?
                  AND w.year = ?";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(1, $game_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $weekNum, PDO::PARAM_INT);
        $stmt->bindParam(3, $year, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getWeekById($week_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM week WHERE id = :week_id");
        $stmt->execute(['week_id' => $week_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAttendanceForPersonGameWeek($name, $game_id, $week_id)
    {
        $ip = '';
        if (getenv('HTTP_X_FORWARDED_FOR')) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $stmt = $this->pdo->prepare("SELECT * FROM attendance a JOIN person p ON a.person_id = p.id WHERE p.name = :name AND p.useragent = :useragent AND a.game_id = :game_id AND a.week_id = :week_id");
        $stmt->execute(['name' => $name, 'ip' => $ip, 'game_id' => $game_id, 'week_id' => $week_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    function upsertAttendanceRecord($person_name, $game_id, $year, $weekNum, $monday, $tuesday, $wednesday, $thursday, $friday, $saturday, $sunday)
    {
        $ip = '';
        if (getenv('HTTP_X_FORWARDED_FOR')) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // Check if the person already exists
        $stmt = $this->pdo->prepare("SELECT id FROM person WHERE name = ? AND ip = ?");
        $stmt->execute([$person_name, $ip]);
        $person_id = $stmt->fetchColumn();

        // If not, insert a new person
        if (!$person_id) {
            $stmt = $this->pdo->prepare("INSERT INTO person (name, ip) VALUES (?, ?)");
            $stmt->execute([$person_name, $ip]);
            $person_id = $this->pdo->lastInsertId();
        }

        // Check if the attendance record already exists
        $stmt = $this->pdo->prepare("SELECT id FROM attendance WHERE person_id = ? AND game_id = ? AND week_id = (SELECT id FROM week WHERE year = ? AND weekNum = ?)");
        $stmt->execute([$person_id, $game_id, $year, $weekNum]);
        $attendance_id = $stmt->fetchColumn();

        // If so, update the record
        if ($attendance_id) {
            $stmt = $this->pdo->prepare("UPDATE attendance SET monday = ?, tuesday = ?, wednesday = ?, thursday = ?, friday = ?, saturday = ?, sunday = ? WHERE id = ?");
            $stmt->execute([$monday, $tuesday, $wednesday, $thursday, $friday, $saturday, $sunday, $attendance_id]);
        }
        // Otherwise, insert a new record
        else {
            $stmt = $this->pdo->prepare("INSERT INTO attendance (person_id, game_id, week_id, monday, tuesday, wednesday, thursday, friday, saturday, sunday) VALUES (?, ?, (SELECT id FROM week WHERE year = ? AND weekNum = ?), ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$person_id, $game_id, $year, $weekNum, $monday, $tuesday, $wednesday, $thursday, $friday, $saturday, $sunday]);
        }
    }

    public function getGames()
    {
        $stmt = $this->pdo->prepare("SELECT * FROM game");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function getPeopleByName($name)
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM person WHERE name = ?");
        $stmt->execute([$name]);
        $people = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $people;
    }
}

$api = new TabletopAPI($host, $db, $username, $password);

if (!isset($_GET['action'])) {
    die('No action specified');
}

if ($_GET['action'] == 'getPersonByNameIP') {
    if (!isset($_GET['name'])) {
        die('No name specified');
    }

    $person = $api->getPersonByNameIP($_GET['name']);
    if ($person) {
        echo json_encode($person);
    } else {
        echo json_encode(['error' => 'No person found']);
    }
}

if ($_GET['action'] == 'getAttendancePGW') {
    if (!isset($_GET['person_id'])) {
        die('No person_id specified');
    }
    if (!isset($_GET['game_id'])) {
        die('No game_id specified');
    }
    if (!isset($_GET['weekNum'])) {
        die('No week_id specified');
    }
    if (!isset($_GET['year'])) {
        die('No week_id specified');
    }
    $attendance = $api->getAttendancePGW($_GET['person_id'], $_GET['game_id'], $_GET['weekNum'], $_GET['year']);
    if ($attendance) {
        echo json_encode($attendance);
    } else {
        echo json_encode(['error' => 'No attendance found']);
    }
}

if ($_GET['action'] == 'upsertUser') {
    if (!isset($_GET['name'])) {
        die('No name specified');
    }

    $api->upsertUser($_GET['name']);
    echo json_encode(['success' => 'User upserted']);
}

if ($_GET['action'] == 'getAttendanceForGameWeek') {
    if (!isset($_GET['game_id'])) {
        die('No game_id specified');
    }
    if (!isset($_GET['weekNum'])) {
        die('No weekNum specified');
    }
    if (!isset($_GET['year'])) {
        die('No year specified');
    }
    $attendance = $api->getAttendanceForGameWeek($_GET['game_id'], $_GET['weekNum'], $_GET['year']);
    if ($attendance) {
        echo json_encode($attendance);
    } else {
        echo json_encode(['error' => 'No attendance found']);
    }
}

if ($_GET['action'] == 'getWeekById') {
    if (!isset($_GET['week_id'])) {
        die('No week_id specified');
    }
    $week = $api->getWeekById($_GET['week_id']);
    if ($week) {
        echo json_encode($week);
    } else {
        echo json_encode(['error' => 'No week found']);
    }
}

if ($_GET['action'] == 'getAttendanceForPersonGameWeek') {
    if (!isset($_GET['name'])) {
        die('No name specified');
    }
    if (!isset($_GET['game_id'])) {
        die('No game_id specified');
    }
    if (!isset($_GET['week_id'])) {
        die('No week_id specified');
    }
    $attendance = $api->getAttendanceForPersonGameWeek($_GET['name'], $_GET['game_id'], $_GET['week_id']);
    if ($attendance) {
        echo json_encode($attendance);
    } else {
        echo json_encode(['error' => 'No attendance found']);
    }
}

if ($_GET['action'] == 'upsertAttendance') {
    if (!isset($_GET['name'])) {
        die('No name specified');
    }
    if (!isset($_GET['game_id'])) {
        die('No game_id specified');
    }
    if (!isset($_GET['year'])) {
        die('No year specified');
    }
    if (!isset($_GET['weekNum'])) {
        die('No weekNum specified');
    }
    if (!isset($_GET['monday'])) {
        die('No monday specified');
    }
    if (!isset($_GET['tuesday'])) {
        die('No tuesday specified');
    }
    if (!isset($_GET['wednesday'])) {
        die('No wednesday specified');
    }
    if (!isset($_GET['thursday'])) {
        die('No thursday specified');
    }
    if (!isset($_GET['friday'])) {
        die('No friday specified');
    }
    if (!isset($_GET['saturday'])) {
        die('No saturday specified');
    }
    if (!isset($_GET['sunday'])) {
        die('No sunday specified');
    }
    $api->upsertAttendanceRecord($_GET['name'], $_GET['game_id'], $_GET['year'], $_GET['weekNum'], $_GET['monday'], $_GET['tuesday'], $_GET['wednesday'], $_GET['thursday'], $_GET['friday'], $_GET['saturday'], $_GET['sunday']);
    echo json_encode(['success' => 'Attendance upserted']);
}

if ($_GET['action'] == 'getGames') {
    $games = $api->getGames();
    if ($games) {
        echo json_encode($games);
    } else {
        echo json_encode(['error' => 'No games found']);
    }
}

if ($_GET['action'] == 'getPeopleByName') {
    if (!isset($_GET['name'])) {
        die('No name specified');
    }
    $people = $api->getPeopleByName($_GET['name']);
    if ($people) {
        echo json_encode($people);
    } else {
        echo json_encode(['error' => 'No people found']);
    }
}
