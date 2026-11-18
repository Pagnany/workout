<?php
$db_server = 'database-5020821545.webspace-host.com';
$db_benutzer = 'dbu3016613';
$db_passwort = '==cTYUR4)TYmX#Ng';
$db_name = 'dbs15849290';

// Verbindungsaufbau
try {
	$dbh = new PDO("mysql:host=$db_server;dbname=$db_name", $db_benutzer, $db_passwort);
	//echo 'Verbindung zur Datenbank hergestellt.';
} catch (PDOException $e) {
	die('Fehler bei der Verbindung zur Datenbank: ' . $e->getMessage());
}
?>
<?php
// Server-side form handling
$errors = [];
$success = false;

// Load regions for form display
try {
	$stmt = $dbh->query('SELECT id, bezeichnung FROM region ORDER BY bezeichnung');
	$regions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	$regions = [];
	$errors[] = 'Fehler beim Laden der Regionen: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$bezeichnung = trim($_POST['bezeichnung'] ?? '');
	$beschreibung = trim($_POST['beschreibung'] ?? '');
	$video_url = trim($_POST['video_url'] ?? '');
	$mehrseitig = isset($_POST['mehrseitig']) ? 1 : 0;
	$selected = $_POST['regions'] ?? [];
	$intensities = $_POST['intensity'] ?? [];

	if ($bezeichnung === '') {
		$errors[] = 'Bezeichnung ist erforderlich.';
	}

	// Validate selected regions and intensities
	$validatedRegions = [];
	foreach ($selected as $regionId) {
		$regionId = (int)$regionId;
		if ($regionId <= 0) continue;
		$intVal = $intensities[$regionId] ?? null;
		if ($intVal === null || $intVal === '') {
			$errors[] = "Intensität für Region $regionId fehlt.";
			continue;
		}
		if (!is_numeric($intVal)) {
			$errors[] = "Intensität für Region $regionId ist keine Zahl.";
			continue;
		}
		$intVal = (int)$intVal;
		if ($intVal < 0 || $intVal > 100) {
			$errors[] = "Intensität für Region $regionId muss zwischen 0 und 100 liegen.";
			continue;
		}
		$validatedRegions[$regionId] = $intVal;
	}

	if (empty($errors)) {
		try {
			$dbh->beginTransaction();
			$sql = 'INSERT INTO uebung (bezeichnung, beschreibung, video_url, mehrseitig) VALUES (:b, :desc, :v, :mehrseitig)';
			$ins = $dbh->prepare($sql);
			$ins->execute([
				':b' => $bezeichnung,
				':desc' => $beschreibung,
				':v' => $video_url,
				':mehrseitig' => $mehrseitig
			]);
			$uebungId = (int)$dbh->lastInsertId();

			if ($uebungId && !empty($validatedRegions)) {
				$stmtUR = $dbh->prepare('INSERT INTO uebung_region (id_region, id_uebung, intensitaet) VALUES (:rid, :uid, :intens)');
				foreach ($validatedRegions as $rid => $intens) {
					$stmtUR->execute([':rid' => $rid, ':uid' => $uebungId, ':intens' => $intens]);
				}
			}

			$dbh->commit();
			$success = true;
		} catch (PDOException $e) {
			$dbh->rollBack();
			$errors[] = 'Datenbankfehler: ' . $e->getMessage();
		}
	}
}
?>

<!doctype html>
<html lang="de">

<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>Übung erstellen</title>
	<style>
		label {
			display: block;
			margin-top: 8px;
		}

		.region {
			margin: 6px 0;
		}

		.intensity {
			width: 80px;
		}

		.error {
			color: #b00;
		}

		.success {
			color: #080;
		}
	</style>
</head>

<body>
	<header class="site-header">
		<h1 class="site-title"><a href="/workout">Workout</a></h1>
	</header>
	<h1>Übung erstellen</h1>

	<?php if ($success): ?>
		<p class="success">Übung wurde erfolgreich erstellt.</p>
	<?php endif; ?>

	<?php if (!empty($errors)): ?>
		<div class="error">
			<ul>
				<?php foreach ($errors as $e): ?>
					<li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post">
		<label>Bezeichnung: <input type="text" name="bezeichnung" value="<?php echo htmlspecialchars($_POST['bezeichnung'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></label>
		<label>Beschreibung: <textarea name="beschreibung"><?php echo htmlspecialchars($_POST['beschreibung'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></label>
		<label>Video-URL: <input type="url" name="video_url" value="<?php echo htmlspecialchars($_POST['video_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
		<label><input type="checkbox" name="mehrseitig" value="1" <?php if (isset($_POST['mehrseitig'])) echo 'checked'; ?>> Mehrseitig</label>

		<fieldset>
			<legend>Regionen (wähle mindestens eine, Intensität 0–100)</legend>
			<?php if (empty($regions)): ?>
				<p>Keine Regionen verfügbar. Lege zuerst Regionen an.</p>
			<?php else: ?>
				<?php foreach ($regions as $r): $id = (int)$r['id']; ?>
					<div class="region">
						<label>
							<input type="checkbox" name="regions[]" value="<?php echo $id; ?>" id="r_<?php echo $id; ?>"
								<?php if (in_array($id, $_POST['regions'] ?? [], true)) echo 'checked'; ?>>
							<?php echo htmlspecialchars($r['bezeichnung'], ENT_QUOTES, 'UTF-8'); ?>
						</label>
						Intensität: <input class="intensity" type="number" name="intensity[<?php echo $id; ?>]" min="0" max="100" value="<?php echo htmlspecialchars($_POST['intensity'][$id] ?? '50', ENT_QUOTES, 'UTF-8'); ?>" id="i_<?php echo $id; ?>">
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</fieldset>

		<p><button type="submit">Erstellen</button></p>
	</form>

	<script>
		// Disable intensity inputs when region checkbox not checked
		(function() {
			document.querySelectorAll('.region').forEach(function(div) {
				var cb = div.querySelector('input[type=checkbox]');
				var input = div.querySelector('input[type=number]');

				function update() {
					input.disabled = !cb.checked;
				}
				cb.addEventListener('change', update);
				update();
			});
		})();
	</script>
</body>

</html>

<!-- 
Tabellen:
uebung(id,bezeichnung,beschreibung,video_url,mehrseitig)
region(id,bezeichnung)
uebung_region(id_region,id_uebung,intensitaet)
-->