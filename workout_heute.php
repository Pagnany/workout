<?php
$db_server = 'database-5020821545.webspace-host.com';
$db_benutzer = 'dbu3016613';
$db_passwort = '==cTYUR4)TYmX#Ng';
$db_name = 'dbs15849290';

$dbh = null;
$uebungen = [];
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
} catch (PDOException $e) {
	error_log('Fehler bei der Datenbankverbindung: ' . $e->getMessage());
	$error = 'Das Workout konnte gerade nicht geladen werden. Bitte versuche es später erneut.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	header('Content-Type: application/json; charset=utf-8');

	if ($dbh === null) {
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Die Datenbank ist gerade nicht erreichbar.']);
		exit;
	}

	$action = $_POST['action'] ?? '';
	if ($action !== 'start' && $action !== 'finish') {
		http_response_code(400);
		echo json_encode(['success' => false, 'message' => 'Ungültige Workout-Aktion.']);
		exit;
	}

	if (!session_start()) {
		error_log('Workout-Sitzung konnte nicht gestartet werden.');
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Das Workout konnte nicht gespeichert werden.']);
		exit;
	}

	try {
		if ($action === 'start') {
			$startedAt = $dbh->query('SELECT NOW()')->fetchColumn();
			$_SESSION['workout_started_at'] = $startedAt;
			unset($_SESSION['completed_workout_id']);

			echo json_encode(['success' => true]);
			exit;
		}

		if (isset($_SESSION['completed_workout_id'])) {
			echo json_encode(['success' => true]);
			exit;
		}

		if (!isset($_SESSION['workout_started_at'])) {
			http_response_code(409);
			echo json_encode(['success' => false, 'message' => 'Kein gestartetes Workout zum Speichern gefunden.']);
			exit;
		}

		$stmt = $dbh->prepare('INSERT INTO workouts (`start`, `ende`) VALUES (:start, NOW())');
		$stmt->execute([':start' => $_SESSION['workout_started_at']]);
		$_SESSION['completed_workout_id'] = (int) $dbh->lastInsertId();
		unset($_SESSION['workout_started_at']);

		echo json_encode(['success' => true]);
		exit;
	} catch (PDOException $e) {
		error_log('Fehler beim Speichern des Workouts: ' . $e->getMessage());
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Das Workout konnte nicht gespeichert werden. Bitte versuche es erneut.']);
		exit;
	}
}

if ($dbh !== null) {
	try {
		$uebungen = $dbh->query(
			'SELECT id, bezeichnung, beschreibung, video_url FROM uebung'
		)->fetchAll();
		shuffle($uebungen);
	} catch (PDOException $e) {
		error_log('Fehler beim Laden des Tages-Workouts: ' . $e->getMessage());
		$error = 'Das Workout konnte gerade nicht geladen werden. Bitte versuche es später erneut.';
	}
}

$anzahlUebungen = count($uebungen);
$sekundenProUebung = $anzahlUebungen > 0 ? (int) ceil(1200 / $anzahlUebungen) : 0;
$aktiveTrainingszeit = $sekundenProUebung * $anzahlUebungen;
$gesamtzeit = $aktiveTrainingszeit + max(0, $anzahlUebungen - 1) * 10;

function workoutDauer(int $sekunden): string
{
	$minuten = intdiv($sekunden, 60);
	$restSekunden = $sekunden % 60;

	if ($restSekunden === 0) {
		return $minuten . ' Minuten';
	}

	if ($minuten === 0) {
		return $restSekunden . ' Sekunden';
	}

	return $minuten . ' Minuten ' . $restSekunden . ' Sekunden';
}
?>
<!doctype html>
<html lang="de">

<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>Workout für heute</title>
	<style>
		body {
			font-family: Arial, sans-serif;
			max-width: 760px;
			margin: 2rem auto;
			padding: 0 1rem;
			line-height: 1.5;
			color: #202124;
		}

		.workout,
		.uebung {
			border: 1px solid #ddd;
			border-radius: 10px;
			padding: 1rem 1.25rem;
			margin-bottom: 1rem;
		}

		.workout {
			text-align: center;
		}

		.timer {
			font-size: clamp(3rem, 12vw, 5rem);
			font-weight: bold;
			font-variant-numeric: tabular-nums;
			margin: 0.25rem 0;
		}

		.buttons {
			display: flex;
			justify-content: center;
			gap: 0.75rem;
			margin-top: 1rem;
		}

		button {
			font: inherit;
			padding: 0.65rem 1.1rem;
			cursor: pointer;
		}

		button:disabled {
			cursor: not-allowed;
		}

		.progress {
			width: 100%;
			height: 0.75rem;
			margin-top: 1rem;
		}

		.beschreibung {
			white-space: pre-wrap;
			overflow-wrap: anywhere;
		}

		.error {
			color: #b00;
		}
	</style>
</head>

<body>
	<header class="site-header">
		<h1 class="site-title"><a href="/workout">Workout</a></h1>
	</header>
	<main>
		<h1>Workout für heute</h1>

		<?php if ($error !== ''): ?>
			<p class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
		<?php elseif ($anzahlUebungen === 0): ?>
			<p>Es sind noch keine Übungen vorhanden. Lege zuerst Übungen an.</p>
			<p><a href="uebung_erstellen.php">Übung erstellen</a></p>
		<?php else: ?>
			<section class="workout" aria-label="Workout-Timer">
				<p id="status" aria-live="polite">Bereit? Starte dein Workout, wenn du so weit bist.</p>
				<p id="current-exercise" aria-live="polite">Insgesamt <?php echo $anzahlUebungen; ?> Übungen</p>
				<div class="timer" id="timer" role="timer" aria-label="Verbleibende Zeit">--:--</div>
				<p>
					<?php echo $anzahlUebungen; ?> Übungen mit je
					<?php echo htmlspecialchars(workoutDauer($sekundenProUebung), ENT_QUOTES, 'UTF-8'); ?> Trainingszeit.
					Aktive Trainingszeit: mindestens 20 Minuten.
					Geschätzte Gesamtdauer mit Pausen: etwa
					<?php echo htmlspecialchars(workoutDauer($gesamtzeit), ENT_QUOTES, 'UTF-8'); ?>.
				</p>
				<progress class="progress" id="progress" value="0" max="<?php echo $anzahlUebungen; ?>" aria-label="Workout-Fortschritt"></progress>
				<div class="buttons">
					<button type="button" id="start-button">Workout starten</button>
					<button type="button" id="pause-button" disabled>Pausieren</button>
				</div>
			</section>

			<h2>Übungen</h2>
			<?php foreach ($uebungen as $uebung): ?>
				<article class="uebung">
					<h3><?php echo htmlspecialchars($uebung['bezeichnung'], ENT_QUOTES, 'UTF-8'); ?></h3>
					<?php if (!empty($uebung['beschreibung'])): ?>
						<p class="beschreibung"><?php echo nl2br(htmlspecialchars($uebung['beschreibung'], ENT_QUOTES, 'UTF-8')); ?></p>
					<?php endif; ?>
					<?php if (!empty($uebung['video_url'])): ?>
						<p><a href="<?php echo htmlspecialchars($uebung['video_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Übungsvideo öffnen</a></p>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>

			<script>
				const exercises = <?php
					echo json_encode(
						array_map(
							static function (array $uebung): array {
								return ['name' => $uebung['bezeichnung']];
							},
							$uebungen
						),
						JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
					);
				?>;
				const exerciseSeconds = <?php echo $sekundenProUebung; ?>;
				const breakSeconds = 10;
				const timerElement = document.getElementById('timer');
				const statusElement = document.getElementById('status');
				const currentExerciseElement = document.getElementById('current-exercise');
				const progressElement = document.getElementById('progress');
				const startButton = document.getElementById('start-button');
				const pauseButton = document.getElementById('pause-button');

				let currentIndex = 0;
				let phase = 'idle';
				let pausedPhase = 'exercise';
				let deadline = 0;
				let pausedRemaining = 0;
				let intervalId = null;

				function formatTime(seconds) {
					const minutes = Math.floor(seconds / 60);
					const remainder = seconds % 60;
					return String(minutes).padStart(2, '0') + ':' + String(remainder).padStart(2, '0');
				}

				function showExercise() {
					currentExerciseElement.textContent =
						'Übung ' + (currentIndex + 1) + ' von ' + exercises.length + ': ' + exercises[currentIndex].name;
				}

				function beginPhase(nextPhase, seconds) {
					phase = nextPhase;
					deadline = Date.now() + seconds * 1000;
					tick();
				}

				async function sendWorkoutAction(action) {
					const response = await fetch(window.location.href, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({ action: action })
					});
					const result = await response.json();
					if (!response.ok || !result.success) {
						throw new Error(result.message || 'Das Workout konnte nicht gespeichert werden.');
					}
				}

				async function finishWorkout() {
					phase = 'saving';
					clearInterval(intervalId);
					intervalId = null;
					timerElement.textContent = '00:00';
					statusElement.textContent = 'Workout beendet. Die Trainingsdaten werden gespeichert ...';
					currentExerciseElement.textContent = 'Alle ' + exercises.length + ' Übungen abgeschlossen.';
					progressElement.value = exercises.length;
					pauseButton.disabled = true;
					startButton.disabled = true;

					try {
						await sendWorkoutAction('finish');
						phase = 'done';
						statusElement.textContent = 'Geschafft! Dein Workout wurde gespeichert.';
						startButton.disabled = false;
						startButton.textContent = 'Noch einmal starten';
					} catch (error) {
						phase = 'save-error';
						statusElement.textContent = error.message + ' Du kannst es erneut versuchen.';
						startButton.disabled = false;
						startButton.textContent = 'Speichern erneut versuchen';
					}
				}

				function advance() {
					if (phase === 'exercise') {
						progressElement.value = currentIndex + 1;
						if (currentIndex === exercises.length - 1) {
							finishWorkout();
							return;
						}

						phase = 'break';
						statusElement.textContent = 'Pause – die nächste Übung startet gleich.';
						currentExerciseElement.textContent =
							'Als Nächstes: ' + exercises[currentIndex + 1].name;
						deadline = Date.now() + breakSeconds * 1000;
					} else if (phase === 'break') {
						currentIndex++;
						showExercise();
						statusElement.textContent = 'Jetzt trainieren!';
						phase = 'exercise';
						deadline = Date.now() + exerciseSeconds * 1000;
					}
				}

				function tick() {
					if (phase !== 'exercise' && phase !== 'break') {
						return;
					}

					let remaining = Math.ceil((deadline - Date.now()) / 1000);
					while (remaining <= 0 && (phase === 'exercise' || phase === 'break')) {
						advance();
						if (phase !== 'exercise' && phase !== 'break') {
							return;
						}
						remaining = Math.ceil((deadline - Date.now()) / 1000);
					}

					timerElement.textContent = formatTime(remaining);
					timerElement.setAttribute('aria-label', 'Verbleibende Zeit: ' + remaining + ' Sekunden');
				}

				startButton.addEventListener('click', async function() {
					if (phase === 'save-error') {
						finishWorkout();
						return;
					}

					startButton.disabled = true;
					statusElement.textContent = 'Workout wird gestartet ...';
					try {
						await sendWorkoutAction('start');
						currentIndex = 0;
						progressElement.value = 0;
						showExercise();
						statusElement.textContent = 'Jetzt trainieren!';
						pauseButton.disabled = false;
						pauseButton.textContent = 'Pausieren';
						beginPhase('exercise', exerciseSeconds);
						intervalId = window.setInterval(tick, 250);
					} catch (error) {
						phase = 'idle';
						statusElement.textContent = error.message;
						startButton.disabled = false;
					}
				});

				pauseButton.addEventListener('click', function() {
					if (phase === 'exercise' || phase === 'break') {
						pausedPhase = phase;
						pausedRemaining = Math.max(0, deadline - Date.now());
						clearInterval(intervalId);
						intervalId = null;
						phase = 'paused';
						statusElement.textContent = 'Workout pausiert.';
						pauseButton.textContent = 'Fortsetzen';
					} else if (phase === 'paused') {
						phase = pausedPhase;
						deadline = Date.now() + pausedRemaining;
						statusElement.textContent = phase === 'break' ? 'Pause – die nächste Übung startet gleich.' : 'Jetzt trainieren!';
						pauseButton.textContent = 'Pausieren';
						tick();
						if (phase === 'exercise' || phase === 'break') {
							intervalId = window.setInterval(tick, 250);
						}
					}
				});
			</script>
		<?php endif; ?>
	</main>
</body>

</html>
