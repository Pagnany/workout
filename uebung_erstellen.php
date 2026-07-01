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

<html>

<head>
	<title>Übung erstellen</title>
	<link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
	<link rel="shortcut icon" href="/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
	<meta name="apple-mobile-web-app-title" content="MyWebSite" />
	<link rel="manifest" href="/site.webmanifest" />
</head>

<body>
	<h1>Übung erstellen</h1>
</body>

</html>

<!-- 
Tabellen:
uebung(id,bezeichnung,beschreibung,video_url)
region(id,bezeichnung)
uebung_region(id_region,id_uebung,intensitaet)
-->