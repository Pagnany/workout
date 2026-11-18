<?php
$db_server = 'database-5020821545.webspace-host.com';
$db_benutzer = 'dbu3016613';
$db_passwort = '==cTYUR4)TYmX#Ng';
$db_name = 'dbs15849290';

try {
	$dbh = new PDO("mysql:host=$db_server;dbname=$db_name", $db_benutzer, $db_passwort);
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
	die('Fehler bei der Verbindung zur Datenbank: ' . $e->getMessage());
}

$uebungen = [];
$error = '';

try {
	$stmt = $dbh->query(
		'SELECT u.id, u.bezeichnung, u.beschreibung, u.video_url, u.mehrseitig, ' .
			'r.id AS region_id, r.bezeichnung AS region_bezeichnung, ur.intensitaet ' .
			'FROM uebung u ' .
			'LEFT JOIN uebung_region ur ON ur.id_uebung = u.id ' .
			'LEFT JOIN region r ON r.id = ur.id_region ' .
			'ORDER BY u.bezeichnung ASC, r.bezeichnung ASC'
	);

	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($rows as $row) {
		$id = (int) $row['id'];

		if (!isset($uebungen[$id])) {
			$uebungen[$id] = [
				'id' => $id,
				'bezeichnung' => $row['bezeichnung'],
				'beschreibung' => $row['beschreibung'],
				'video_url' => $row['video_url'],
				'mehrseitig' => (bool) $row['mehrseitig'],
				'regionen' => []
			];
		}

		if (!empty($row['region_id'])) {
			$uebungen[$id]['regionen'][] = [
				'id' => (int) $row['region_id'],
				'bezeichnung' => $row['region_bezeichnung'],
				'intensitaet' => (int) $row['intensitaet']
			];
		}
	}
} catch (PDOException $e) {
	$error = 'Fehler beim Laden der Übungen: ' . $e->getMessage();
}
?>

<!doctype html>
<html lang="de">

<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>Übungen</title>
	<link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
	<link rel="shortcut icon" href="/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
	<meta name="apple-mobile-web-app-title" content="MyWebSite" />
	<link rel="manifest" href="/site.webmanifest" />
	<style>
		body {
			font-family: Arial, sans-serif;
			max-width: 900px;
			margin: 2rem auto;
			padding: 0 1rem;
			line-height: 1.5;
		}

		.uebung {
			border: 1px solid #ddd;
			border-radius: 8px;
			padding: 1rem 1.25rem;
			margin-bottom: 1rem;
		}

		.beschreibung {
			white-space: pre-wrap;
			overflow-wrap: anywhere;
		}

		.regionen {
			margin: 0.5rem 0 0;
			padding-left: 1.2rem;
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
	<h1>Alle Übungen</h1>

	<?php if ($error !== ''): ?>
		<p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
	<?php endif; ?>

	<?php if (empty($uebungen)): ?>
		<p>Es sind noch keine Übungen vorhanden.</p>
	<?php else: ?>
		<?php foreach ($uebungen as $uebung): ?>
			<article class="uebung">
				<h2><?php echo htmlspecialchars($uebung['bezeichnung'], ENT_QUOTES, 'UTF-8'); ?></h2>
				<p class="beschreibung"><?php echo nl2br(htmlspecialchars($uebung['beschreibung'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>
				<p><strong>Mehrseitig:</strong> <?php echo $uebung['mehrseitig'] ? 'Ja' : 'Nein'; ?></p>

				<?php if (!empty($uebung['video_url'])): ?>
					<p><strong>Video:</strong> <a href="<?php echo htmlspecialchars($uebung['video_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Öffnen</a></p>
				<?php endif; ?>

				<p><strong>Regionen:</strong></p>
				<?php if (!empty($uebung['regionen'])): ?>
					<ul class="regionen">
						<?php foreach ($uebung['regionen'] as $region): ?>
							<li><?php echo htmlspecialchars($region['bezeichnung'], ENT_QUOTES, 'UTF-8'); ?> – Intensität: <?php echo (int) $region['intensitaet']; ?>%</li>
						<?php endforeach; ?>
					</ul>
				<?php else: ?>
					<p>Keine Regionen zugeordnet.</p>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	<?php endif; ?>
</body>

</html>

<!--
Tabellen:
uebung(id,bezeichnung,beschreibung,video_url,mehrseitig)
region(id,bezeichnung)
uebung_region(id_region,id_uebung,intensitaet)
-->