<?php
$db_server = 'database-5020821545.webspace-host.com';
$db_benutzer = 'dbu3016613';
$db_passwort = '==cTYUR4)TYmX#Ng';
$db_name = 'dbs15849290';

// Verbindungsaufbau
try {
	$dbh = new PDO("mysql:host=$db_server;dbname=$db_name", $db_benutzer, $db_passwort);
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
	die('Fehler bei der Verbindung zur Datenbank: ' . $e->getMessage());
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$bezeichnung = trim($_POST['bezeichnung'] ?? '');

	if ($bezeichnung === '') {
		$error = 'Bitte geben Sie eine Bezeichnung ein.';
	} else {
		try {
			$stmt = $dbh->prepare('INSERT INTO region (bezeichnung) VALUES (:bezeichnung)');
			$stmt->execute(['bezeichnung' => $bezeichnung]);
			$message = 'Region "' . htmlspecialchars($bezeichnung) . '" wurde erfolgreich angelegt.';
		} catch (PDOException $e) {
			$error = 'Fehler beim Anlegen der Region: ' . $e->getMessage();
		}
	}
}

$regionen = $dbh->query('SELECT id, bezeichnung FROM region ORDER BY bezeichnung ASC')->fetchAll(PDO::FETCH_ASSOC);
?>

<html>

<head>
	<title>Region erstellen</title>
	<link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
	<link rel="shortcut icon" href="/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
	<meta name="apple-mobile-web-app-title" content="MyWebSite" />
	<link rel="manifest" href="/site.webmanifest" />
</head>

<body>
	<header class="site-header">
		<h1 class="site-title"><a href="/workout">Workout</a></h1>
	</header>
	<h1>Region erstellen</h1>

	<?php if ($message !== ''): ?>
		<p style="color: green;"><?php echo htmlspecialchars($message); ?></p>
	<?php endif; ?>

	<?php if ($error !== ''): ?>
		<p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
	<?php endif; ?>

	<h2>Neue Region anlegen</h2>
	<form method="post" action="region_erstellen.php">
		<label for="bezeichnung">Bezeichnung:</label>
		<input type="text" id="bezeichnung" name="bezeichnung" required>
		<button type="submit">Region anlegen</button>
	</form>

	<h2>Vorhandene Regionen</h2>
	<?php if (count($regionen) > 0): ?>
		<ul>
			<?php foreach ($regionen as $region): ?>
				<li><?php echo htmlspecialchars($region['bezeichnung']); ?> (ID: <?php echo (int)$region['id']; ?>)</li>
			<?php endforeach; ?>
		</ul>
	<?php else: ?>
		<p>Es sind noch keine Regionen vorhanden.</p>
	<?php endif; ?>
</body>

</html>

<!-- 
Tabellen:
uebung(id,bezeichnung,beschreibung,video_url)
region(id,bezeichnung)
uebung_region(id_region,id_uebung,intensitaet)
-->