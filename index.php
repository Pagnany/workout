<?php
$db_server = 'database-5020821545.webspace-host.com';
$db_benutzer = 'dbu3016613';
$db_passwort = '==cTYUR4)TYmX#Ng';
$db_name = 'dbs15849290';

$today = new DateTimeImmutable('today');
$currentMonth = $today->modify('first day of this month');
$requestedMonth = $_GET['monat'] ?? '';
if (!is_string($requestedMonth)) {
	$requestedMonth = '';
}
$selectedMonth = DateTimeImmutable::createFromFormat('!Y-m', $requestedMonth);

if (
	$selectedMonth === false ||
	$selectedMonth->format('Y-m') !== $requestedMonth ||
	$selectedMonth > $currentMonth
) {
	$selectedMonth = $currentMonth;
}

$monthStart = $selectedMonth->modify('first day of this month');
$nextMonth = $monthStart->modify('+1 month');
$previousMonth = $monthStart->modify('-1 month');
$monthNames = [
	1 => 'Januar',
	2 => 'Februar',
	3 => 'März',
	4 => 'April',
	5 => 'Mai',
	6 => 'Juni',
	7 => 'Juli',
	8 => 'August',
	9 => 'September',
	10 => 'Oktober',
	11 => 'November',
	12 => 'Dezember'
];
$monthTitle = $monthNames[(int) $monthStart->format('n')] . ' ' . $monthStart->format('Y');
$workoutDates = [];
$streak = 0;
$error = '';

try {
	$dbh = new PDO(
		"mysql:host=$db_server;dbname=$db_name;charset=utf8mb4",
		$db_benutzer,
		$db_passwort,
		[
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		]
	);

	$monthQuery = $dbh->prepare(
		'SELECT DISTINCT DATE(`start`) AS workout_date FROM workouts ' .
		'WHERE `ende` IS NOT NULL AND `start` >= :month_start AND `start` < :next_month'
	);
	$monthQuery->execute([
		':month_start' => $monthStart->format('Y-m-d'),
		':next_month' => $nextMonth->format('Y-m-d')
	]);
	foreach ($monthQuery->fetchAll() as $row) {
		$workoutDates[$row['workout_date']] = true;
	}

	$streakQuery = $dbh->prepare(
		'SELECT DISTINCT DATE(`start`) AS workout_date FROM workouts ' .
		'WHERE `ende` IS NOT NULL AND `start` < :tomorrow ORDER BY workout_date DESC'
	);
	$streakQuery->execute([':tomorrow' => $today->modify('+1 day')->format('Y-m-d')]);
	$streakDates = $streakQuery->fetchAll(PDO::FETCH_COLUMN);

	if ($streakDates !== []) {
		$latestWorkoutDate = new DateTimeImmutable($streakDates[0]);
		$daysSinceLastWorkout = (int) $latestWorkoutDate->diff($today)->format('%r%a');

		if ($daysSinceLastWorkout >= 0 && $daysSinceLastWorkout <= 1) {
			$expectedDate = $latestWorkoutDate;
			foreach ($streakDates as $date) {
				$workoutDate = new DateTimeImmutable($date);
				if ($workoutDate->format('Y-m-d') !== $expectedDate->format('Y-m-d')) {
					break;
				}
				$streak++;
				$expectedDate = $expectedDate->modify('-1 day');
			}
		}
	}
} catch (PDOException $e) {
	error_log('Fehler beim Laden des Trainingskalenders: ' . $e->getMessage());
	$error = 'Der Trainingskalender konnte gerade nicht geladen werden. Bitte versuche es später erneut.';
}

$weekdayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
$emptyDays = (int) $monthStart->format('N') - 1;
?>
<!doctype html>
<html lang="de">

<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Workout</title>
	<link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
	<link rel="shortcut icon" href="/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
	<meta name="apple-mobile-web-app-title" content="MyWebSite" />
	<link rel="manifest" href="/site.webmanifest" />
	<link rel="stylesheet" href="style.css" />
</head>

<body>
	<header>
		<div class="site-header-inner">
			<h1 class="site-title"><a href="/workout">Workout</a></h1>
			<nav class="site-nav" aria-label="Hauptnavigation">
				<a href="index.php" aria-current="page">Übersicht</a>
				<a href="workout_heute.php">Workout</a>
				<a href="uebungen.php">Übungen</a>
				<a href="uebung_erstellen.php">Übung erstellen</a>
				<a href="region_erstellen.php">Region erstellen</a>
			</nav>
		</div>
	</header>

	<main class="page-content">
		<h2>Dein Trainingsverlauf</h2>
		<?php if ($error !== ''): ?>
			<p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
		<?php endif; ?>

		<section class="calendar-card" aria-label="Trainingskalender">
			<div class="calendar-heading">
				<nav class="month-navigation" aria-label="Monat wechseln">
					<a href="?monat=<?php echo $previousMonth->format('Y-m'); ?>" aria-label="Vorheriger Monat">&larr;</a>
				</nav>
				<h2><?php echo htmlspecialchars($monthTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
				<nav class="month-navigation" aria-label="Monat wechseln">
					<a
						href="?monat=<?php echo $nextMonth->format('Y-m'); ?>"
						aria-label="Nächster Monat"
						<?php echo $nextMonth > $currentMonth ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
					>&rarr;</a>
				</nav>
			</div>

			<div class="calendar-grid" aria-label="<?php echo htmlspecialchars($monthTitle, ENT_QUOTES, 'UTF-8'); ?>">
				<?php foreach ($weekdayNames as $weekday): ?>
					<span class="weekday"><?php echo $weekday; ?></span>
				<?php endforeach; ?>

				<?php for ($i = 0; $i < $emptyDays; $i++): ?>
					<span aria-hidden="true"></span>
				<?php endfor; ?>

				<?php for ($day = $monthStart; $day < $nextMonth; $day = $day->modify('+1 day')): ?>
					<?php
					$date = $day->format('Y-m-d');
					$hasWorkout = isset($workoutDates[$date]);
					$isToday = $date === $today->format('Y-m-d');
					$classes = 'calendar-day' . ($hasWorkout ? ' has-workout' : '') . ($isToday ? ' is-today' : '');
					$label = $day->format('d.m.Y') . ($hasWorkout ? ', Workout' : '') . ($isToday ? ', heute' : '');
					?>
					<span class="<?php echo $classes; ?>" aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
						<?php echo $day->format('j'); ?>
					</span>
				<?php endfor; ?>
			</div>

			<p class="calendar-legend"><span class="legend-dot" aria-hidden="true"></span> Workout abgeschlossen</p>
			<p class="streak">
				<?php if ($streak > 0): ?>
					🔥 Aktuelle Streak: <strong><?php echo $streak; ?> <?php echo $streak === 1 ? 'Tag' : 'Tage'; ?></strong>
				<?php else: ?>
					💪 Noch keine aktive Streak. Starte heute mit einem Workout!
				<?php endif; ?>
			</p>
		</section>

		<nav class="actions" aria-label="Workout-Navigation">
			<a href="workout_heute.php">Workout für heute starten</a>
		</nav>
	</main>
</body>

</html>

<!--
Tabellen:
uebung(id,bezeichnung,beschreibung,video_url,mehrseitig)
region(id,bezeichnung)
uebung_region(id_region,id_uebung,intensitaet)
workouts(id,start,ende)
-->
