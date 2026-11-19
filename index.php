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
	<style>
		:root {
			color-scheme: light;
			font-family: Arial, sans-serif;
			color: #17231d;
			background: #f3f6f3;
		}

		body {
			max-width: 760px;
			margin: 0 auto;
			padding: 2rem 1rem;
			line-height: 1.5;
		}

		a {
			color: #176b45;
		}

		.site-title {
			margin: 0 0 1.5rem;
		}

		.site-title a {
			color: inherit;
			text-decoration: none;
		}

		.calendar-card {
			padding: 1.5rem;
			margin: 1.5rem 0;
			background: #fff;
			border: 1px solid #dfe7e0;
			border-radius: 16px;
			box-shadow: 0 8px 24px #17231d0a;
		}

		.calendar-heading,
		.month-navigation {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 1rem;
		}

		.calendar-heading h2 {
			margin: 0;
			font-size: 1.35rem;
		}

		.month-navigation a {
			display: grid;
			width: 2.5rem;
			height: 2.5rem;
			place-items: center;
			border: 1px solid #dfe7e0;
			border-radius: 50%;
			font-size: 1.4rem;
			line-height: 1;
			text-decoration: none;
		}

		.month-navigation a[aria-disabled="true"] {
			color: #a5afa8;
			pointer-events: none;
		}

		.calendar-grid {
			display: grid;
			grid-template-columns: repeat(7, minmax(0, 1fr));
			gap: 0.35rem;
			margin-top: 1rem;
			text-align: center;
		}

		.weekday {
			padding: 0.35rem 0;
			color: #66736b;
			font-size: 0.82rem;
			font-weight: 700;
		}

		.calendar-day {
			position: relative;
			display: grid;
			min-height: 2.7rem;
			place-items: center;
			border-radius: 10px;
			font-size: 0.95rem;
		}

		.calendar-day.has-workout {
			color: #fff;
			background: #218253;
			font-weight: 700;
		}

		.calendar-day.is-today:not(.has-workout) {
			outline: 2px solid #218253;
			outline-offset: -2px;
			font-weight: 700;
		}

		.calendar-legend {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			margin: 1rem 0 0;
			color: #526057;
			font-size: 0.9rem;
		}

		.legend-dot {
			width: 0.8rem;
			height: 0.8rem;
			border-radius: 50%;
			background: #218253;
		}

		.streak {
			padding: 1rem 1.1rem;
			margin-top: 1rem;
			background: #f0f7f2;
			border-radius: 12px;
		}

		.streak strong {
			color: #176b45;
		}

		.error {
			color: #a52222;
		}

		.actions {
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem;
			margin-top: 1.5rem;
		}

		.actions a {
			padding: 0.7rem 1rem;
			border: 1px solid #dfe7e0;
			border-radius: 9px;
			background: #fff;
			text-decoration: none;
		}

		.actions a:first-child {
			color: #fff;
			border-color: #176b45;
			background: #176b45;
		}

		@media (max-width: 420px) {
			body {
				padding: 1rem 0.75rem;
			}

			.calendar-card {
				padding: 1rem 0.75rem;
			}

			.calendar-grid {
				gap: 0.15rem;
			}

			.calendar-day {
				min-height: 2.4rem;
			}
		}
	</style>
</head>

<body>
	<header>
		<h1 class="site-title"><a href="/workout">Workout</a></h1>
	</header>

	<main>
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
			<a href="uebungen.php">Alle Übungen</a>
			<a href="uebung_erstellen.php">Übung erstellen</a>
			<a href="region_erstellen.php">Region erstellen</a>
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
