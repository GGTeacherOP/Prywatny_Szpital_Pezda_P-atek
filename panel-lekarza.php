<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Sprawdzenie czy użytkownik jest zalogowany i jest lekarzem
if (!isset($_SESSION['user_id']) || $_SESSION['funkcja'] !== 'lekarz') {
    header("Location: logowanie.php");
    exit();
}

// Połączenie z bazą danych
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "szpital";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obsługa zmiany statusu wizyty
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['visit_id'])) {
        $visit_id = $_POST['visit_id'];
        $stmt = $conn->prepare("UPDATE visits SET status = 'zakończona' WHERE id = :visit_id");
        $stmt->bindParam(':visit_id', $visit_id);
        $stmt->execute();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }
    
    // Pobieranie danych lekarza
    $stmt = $conn->prepare("SELECT u.*, d.specjalizacja 
                           FROM users u 
                           JOIN doctors d ON u.id = d.uzytkownik_id 
                           WHERE u.id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $lekarz = $stmt->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    echo "Błąd połączenia: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset='utf-8'>
    <meta http-equiv='X-UA-Compatible' content='IE=edge'>
    <title>Szpital - Panel Lekarza</title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <link rel="icon" type="image/png" href="img/logo/icon.png">
    <link rel='stylesheet' type='text/css' media='screen' href='main.css'>
    <link rel='stylesheet' type='text/css' media='screen' href='css/panel-lekarza.css'>
    <script src='main.js'></script>
    <script src='js/panel-lekarza.js'></script>
</head>
<body>
    <header class="header">
        <div class="logo">
            <img src="img/logo/logo.png" alt="Logo Szpitala">
        </div>
        <nav class="main-nav">
            <ul>
                <li><a href="index.html">Strona główna</a></li>
                <li><a href="o-nas.html">O nas</a></li>
                <li><a href="aktualnosci.html">Aktualności</a></li>
                <li><a href="dla-pacjenta.html">Dla pacjenta</a></li>
            </ul>
        </nav>
        <div class="login-button">
            <a href="index.html" class="btn-login">Wyloguj się</a>
        </div>
    </header>

    <main class="main-content">
        <div class="doctor-dashboard">
            <div class="dashboard-header">
                <h1>Panel Lekarza</h1>
                <div class="doctor-info">
                    <p>Witaj, <span class="doctor-name"><?php echo htmlspecialchars($lekarz['imie'] . ' ' . $lekarz['nazwisko']); ?></span></p>
                    <p>Specjalizacja: <span class="doctor-specialization"><?php echo htmlspecialchars($lekarz['specjalizacja']); ?></span></p>
                </div>
            </div>

            <div class="dashboard-container">
                <!-- Boczny panel nawigacyjny -->
                <nav class="side-nav">
                    <ul>
                        <li>
                            <a href="#" class="nav-item active" data-panel="panel-glowny">
                                <span class="nav-icon">🏠</span>
                                <span class="nav-text">Panel główny</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-item" data-panel="harmonogram">
                                <span class="nav-icon">📅</span>
                                <span class="nav-text">Harmonogram Wizyt</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-item" data-panel="lista-pacjentow">
                                <span class="nav-icon">👥</span>
                                <span class="nav-text">Lista Pacjentów</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-item" data-panel="wystaw-wyniki">
                                <span class="nav-icon">📋</span>
                                <span class="nav-text">Wystaw Wyniki</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nav-item" data-panel="wizyta">
                                <span class="nav-icon">📅</span>
                                <span class="nav-text">Wizyta</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <div class="dashboard-grid">
                    <!-- Panel Główny -->
                    <div class="panel-content" id="panel-glowny">
                        <div class="main-grid">
                            <!-- Sekcja Dzisiejsze Wizyty -->
                            <div class="grid-item">
                                <section class="dashboard-section today-visits">
                                    <h2>Wszystkie Wizyty</h2>
                                    <div class="visits-scroll-container">
                                        <?php
                                        // Pobieranie wszystkich wizyt
                                        $stmt = $conn->prepare("
                                            SELECT 
                                                v.id,
                                                v.data_wizyty,
                                                v.typ_wizyty,
                                                v.status,
                                                v.gabinet,
                                                u.imie,
                                                u.nazwisko
                                            FROM visits v
                                            JOIN patients p ON v.pacjent_id = p.id
                                            JOIN users u ON p.uzytkownik_id = u.id
                                            JOIN doctors d ON v.lekarz_id = d.id
                                            WHERE d.uzytkownik_id = :user_id
                                            ORDER BY v.data_wizyty ASC
                                        ");
                                        $stmt->bindParam(':user_id', $_SESSION['user_id']);
                                        $stmt->execute();
                                        $wizyty = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        if (count($wizyty) > 0) {
                                            foreach ($wizyty as $wizyta) {
                                                echo '<div class="visit-card">';
                                                echo '<div class="visit-info">';
                                                echo '<h3>' . htmlspecialchars($wizyta['imie'] . ' ' . $wizyta['nazwisko']) . '</h3>';
                                                echo '<p class="visit-time">' . date('d.m.Y H:i', strtotime($wizyta['data_wizyty'])) . '</p>';
                                                echo '<p class="visit-type ' . strtolower($wizyta['typ_wizyty']) . '">' . 
                                                     ucfirst(htmlspecialchars($wizyta['typ_wizyty'])) . '</p>';
                                                echo '<p class="visit-room">Gabinet: ' . htmlspecialchars($wizyta['gabinet']) . '</p>';
                                                echo '<p class="visit-status">Status: ' . htmlspecialchars($wizyta['status']) . '</p>';
                                                echo '</div>';
                                                echo '</div>';
                                            }
                                        } else {
                                            echo '<p class="no-visits">Brak zaplanowanych wizyt</p>';
                                        }
                                        ?>
                                    </div>
                                    <style>
                                        .visits-scroll-container {
                                            max-height: 600px;
                                            overflow-y: auto;
                                            padding-right: 10px;
                                            margin-top: 15px;
                                        }
                                        
                                        .visits-scroll-container::-webkit-scrollbar {
                                            width: 8px;
                                        }
                                        
                                        .visits-scroll-container::-webkit-scrollbar-track {
                                            background: #f1f1f1;
                                            border-radius: 4px;
                                        }
                                        
                                        .visits-scroll-container::-webkit-scrollbar-thumb {
                                            background: #888;
                                            border-radius: 4px;
                                        }
                                        
                                        .visits-scroll-container::-webkit-scrollbar-thumb:hover {
                                            background: #555;
                                        }
                                        
                                        .visit-card {
                                            background: white;
                                            border-radius: 8px;
                                            padding: 15px;
                                            margin-bottom: 10px;
                                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                                            transition: transform 0.2s;
                                        }
                                        
                                        .visit-card:hover {
                                            transform: translateY(-2px);
                                            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
                                        }
                                    </style>
                                </section>
                            </div>

                            <!-- Sekcja Ostatnie Wyniki -->
                            <div class="grid-item">
                                <section class="dashboard-section recent-results">
                                    <h2>Ostatnie Wyniki</h2>
                                    <?php
                                    // Pobieranie ostatnich wyników
                                    $stmt = $conn->prepare("SELECT r.*, u.imie, u.nazwisko 
                                                          FROM results r 
                                                          JOIN patients p ON r.pacjent_id = p.id 
                                                          JOIN users u ON p.uzytkownik_id = u.id
                                                          JOIN doctors d ON r.lekarz_id = d.id 
                                                          WHERE d.uzytkownik_id = :user_id 
                                                          ORDER BY r.data_wystawienia DESC 
                                                          LIMIT 5");
                                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                                    $stmt->execute();
                                    $wyniki = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    if (count($wyniki) > 0) {
                                        foreach ($wyniki as $wynik) {
                                            echo '<div class="result-card">';
                                            echo '<div class="result-info">';
                                            echo '<h3>' . htmlspecialchars($wynik['imie'] . ' ' . $wynik['nazwisko']) . '</h3>';
                                            echo '<p class="result-date">' . date('d.m.Y H:i', strtotime($wynik['data_wystawienia'])) . '</p>';
                                            echo '<p class="result-type">' . htmlspecialchars($wynik['typ_badania']) . '</p>';
                                            echo '<p class="result-pin">PIN: ' . htmlspecialchars($wynik['pin']) . '</p>';
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<p class="no-results">Brak wystawionych wyników</p>';
                                    }
                                    ?>
                                </section>
                            </div>

                            <!-- Sekcja Statystyki -->
                            <div class="grid-item full-width">
                                <section class="dashboard-section statistics">
                                    <h2>Statystyki</h2>
                                    <?php
                                    // Pobieranie statystyk
                                    $stmt = $conn->prepare("SELECT 
                                        COUNT(CASE WHEN DATE(v.data_wizyty) = CURDATE() THEN 1 END) as dzisiaj,
                                        COUNT(CASE WHEN DATE(v.data_wizyty) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as tydzien,
                                        COUNT(CASE WHEN DATE(v.data_wizyty) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as miesiac
                                        FROM visits v 
                                        JOIN doctors d ON v.lekarz_id = d.id 
                                        WHERE d.uzytkownik_id = :user_id");
                                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                                    $stmt->execute();
                                    $statystyki = $stmt->fetch(PDO::FETCH_ASSOC);
                                    ?>
                                    <div class="stats-grid">
                                        <div class="stat-card">
                                            <h3>Dzisiaj</h3>
                                            <p class="stat-number"><?php echo $statystyki['dzisiaj']; ?></p>
                                            <p class="stat-label">Wizyt</p>
                                        </div>
                                        <div class="stat-card">
                                            <h3>Ten tydzień</h3>
                                            <p class="stat-number"><?php echo $statystyki['tydzien']; ?></p>
                                            <p class="stat-label">Wizyt</p>
                                        </div>
                                        <div class="stat-card">
                                            <h3>Ten miesiąc</h3>
                                            <p class="stat-number"><?php echo $statystyki['miesiac']; ?></p>
                                            <p class="stat-label">Wizyt</p>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>

                    <!-- Harmonogram -->
                    <div class="panel-content" id="harmonogram" style="display: none;">
                        <h2>Harmonogram Wizyt</h2>
                        <div class="schedule-container">
                            <table class="schedule-table">
                                <thead>
                                    <tr>
                                        <th>Godzina</th>
                                        <?php
                                        // Pobieranie dni tygodnia
                                        $dni = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];
                                        $dzisiejszy_dzien = date('w'); // 0 (niedziela) do 6 (sobota)
                                        
                                        // Obliczanie dat dla dni tygodnia
                                        $daty = [];
                                        for ($i = 0; $i <= 6; $i++) {
                                            $roznica = $i - $dzisiejszy_dzien;
                                            $data = date('Y-m-d', strtotime("$roznica days"));
                                            $daty[] = $data;
                                            error_log("Generowana data: $data");
                                            $klasa_dnia = ($i == 0 || $i == 6) ? 'weekend' : '';
                                            echo "<th class='$klasa_dnia'>" . $dni[$i] . "<br><span class='date'>" . date('d.m', strtotime($data)) . "</span></th>";
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Godziny przyjęć
                                    $godziny = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00'];
                                    
                                    // Pobieranie wszystkich wizyt
                                    $stmt = $conn->prepare("SELECT 
                                        v.*,
                                        d.specjalizacja,
                                        u.imie,
                                        u.nazwisko
                                        FROM visits v 
                                        JOIN doctors d ON v.lekarz_id = d.id 
                                        JOIN users u ON d.uzytkownik_id = u.id
                                        JOIN patients p ON v.pacjent_id = p.id
                                        JOIN users u2 ON p.uzytkownik_id = u2.id
                                        WHERE d.uzytkownik_id = :user_id 
                                        AND DATE(v.data_wizyty) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)
                                        ORDER BY v.data_wizyty ASC");
                                    $stmt->bindParam(':user_id', $_SESSION['user_id']);
                                    $stmt->execute();
                                    $wizyty = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($godziny as $godzina) {
                                        echo "<tr>";
                                        echo "<td class='time-cell'>$godzina</td>";
                                        
                                        foreach ($daty as $data) {
                                            echo "<td class='visit-cell'>";
                                            
                                            // Sprawdzanie czy są wizyty na ten dzień i godzinę
                                            $wizyty_na_dzien = array_filter($wizyty, function($wizyta) use ($data, $godzina) {
                                                $wizyta_data = date('Y-m-d', strtotime($wizyta['data_wizyty']));
                                                $wizyta_godzina = date('H:i', strtotime($wizyta['data_wizyty']));
                                                return $wizyta_data === $data && $wizyta_godzina === $godzina;
                                            });
                                            
                                            if (!empty($wizyty_na_dzien)) {
                                                foreach ($wizyty_na_dzien as $wizyta) {
                                                    $status_class = strtolower($wizyta['status']);
                                                    echo '<div class="visit-slot ' . strtolower($wizyta['typ_wizyty']) . ' ' . $status_class . '">';
                                                    echo '<span class="visit-time">' . date('H:i', strtotime($wizyta['data_wizyty'])) . '</span>';
                                                    echo '<span class="patient-name">' . htmlspecialchars($wizyta['imie'] . ' ' . $wizyta['nazwisko']) . '</span>';
                                                    echo '<span class="visit-type">' . htmlspecialchars($wizyta['typ_wizyty']) . '</span>';
                                                    if ($wizyta['gabinet']) {
                                                        echo '<span class="visit-room">Gabinet: ' . htmlspecialchars($wizyta['gabinet']) . '</span>';
                                                    }
                                                    echo '</div>';
                                                }
                                            } else {
                                                echo '<div class="empty-slot">Wolne</div>';
                                            }
                                            
                                            echo "</td>";
                                        }
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <style>
                            .schedule-container {
                                overflow-x: auto;
                                margin-top: 20px;
                                background: white;
                                border-radius: 8px;
                                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                            }
                            
                            .schedule-table {
                                width: 100%;
                                border-collapse: collapse;
                                min-width: 1000px;
                            }
                            
                            .schedule-table th,
                            .schedule-table td {
                                border: 1px solid #e0e0e0;
                                padding: 10px;
                                text-align: center;
                            }
                            
                            .schedule-table th {
                                background: #f5f5f5;
                                font-weight: bold;
                                position: sticky;
                                top: 0;
                                z-index: 1;
                            }
                            
                            .schedule-table th.weekend {
                                background: #fff0f0;
                            }
                            
                            .time-cell {
                                background: #f5f5f5;
                                font-weight: bold;
                                position: sticky;
                                left: 0;
                                z-index: 1;
                            }
                            
                            .visit-cell {
                                min-width: 150px;
                                height: 80px;
                                vertical-align: top;
                            }
                            
                            .visit-slot {
                                background: #e3f2fd;
                                border-radius: 4px;
                                padding: 8px;
                                margin: 2px;
                                font-size: 0.9em;
                                display: flex;
                                flex-direction: column;
                                gap: 4px;
                            }
                            
                            .visit-slot.pierwsza { background: #e8f5e9; }
                            .visit-slot.kontrolna { background: #fff3e0; }
                            .visit-slot.pogotowie { background: #ffebee; }
                            .visit-slot.szczepienie { background: #e8eaf6; }
                            .visit-slot.badanie { background: #f3e5f5; }
                            
                            .empty-slot {
                                color: #9e9e9e;
                                font-style: italic;
                                font-size: 0.9em;
                            }
                            
                            .visit-time {
                                font-weight: bold;
                                color: #333;
                            }
                            
                            .patient-name {
                                font-weight: 500;
                            }
                            
                            .visit-type {
                                font-size: 0.85em;
                                color: #666;
                            }
                            
                            .visit-room {
                                font-size: 0.85em;
                                color: #666;
                            }
                            
                            .date {
                                font-size: 0.85em;
                                color: #666;
                                display: block;
                                margin-top: 4px;
                            }
                        </style>
                    </div>

                    <!-- Lista Pacjentów -->
                    <div class="panel-content" id="lista-pacjentow" style="display: none;">
                        <h2>Lista Pacjentów</h2>
                        <div class="patients-container">
                            <div class="search-filters">
                                <div class="search-box">
                                    <input type="text" id="patientSearch" placeholder="Szukaj pacjenta...">
                                    <button class="search-btn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <div class="filters">
                                    <select id="sortBy">
                                        <option value="name">Sortuj po imieniu</option>
                                        <option value="surname">Sortuj po nazwisku</option>
                                        <option value="lastVisit">Sortuj po ostatniej wizycie</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="patients-list">
                                <?php
                                // Pobieranie wszystkich pacjentów lekarza
                                $stmt = $conn->prepare("SELECT DISTINCT 
                                    p.id as pacjent_id,
                                    u.imie,
                                    u.nazwisko,
                                    u.pesel,
                                    u.data_urodzenia,
                                    p.grupa_krwi,
                                    MAX(v.data_wizyty) as ostatnia_wizyta,
                                    COUNT(v.id) as liczba_wizyt
                                    FROM patients p 
                                    JOIN users u ON p.uzytkownik_id = u.id
                                    JOIN visits v ON v.pacjent_id = p.id
                                    JOIN doctors d ON v.lekarz_id = d.id
                                    WHERE d.uzytkownik_id = :user_id
                                    GROUP BY p.id, u.imie, u.nazwisko, u.pesel, u.data_urodzenia, p.grupa_krwi
                                    ORDER BY u.nazwisko, u.imie");
                                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                                $stmt->execute();
                                $pacjenci = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (count($pacjenci) > 0) {
                                    foreach ($pacjenci as $pacjent) {
                                        $wiek = date_diff(date_create($pacjent['data_urodzenia']), date_create('today'))->y;
                                        echo '<div class="patient-card" data-pesel="' . htmlspecialchars($pacjent['pesel']) . '">';
                                        echo '<div class="patient-info">';
                                        echo '<div class="patient-main-info">';
                                        echo '<h3>' . htmlspecialchars($pacjent['imie'] . ' ' . $pacjent['nazwisko']) . '</h3>';
                                        echo '<span class="patient-age">' . $wiek . ' lat</span>';
                                        echo '</div>';
                                        echo '<div class="patient-details">';
                                        echo '<p><strong>PESEL:</strong> ' . htmlspecialchars($pacjent['pesel']) . '</p>';
                                        echo '<p><strong>Grupa krwi:</strong> ' . htmlspecialchars($pacjent['grupa_krwi']) . '</p>';
                                        echo '</div>';
                                        echo '<div class="patient-visits-info">';
                                        echo '<p><strong>Ostatnia wizyta:</strong> ' . date('d.m.Y', strtotime($pacjent['ostatnia_wizyta'])) . '</p>';
                                        echo '<p><strong>Liczba wizyt:</strong> ' . $pacjent['liczba_wizyt'] . '</p>';
                                        echo '</div>';
                                        echo '</div>';
                                        echo '<div class="patient-actions">';
                                        echo '<button class="btn-view-history" data-patient-id="' . $pacjent['pacjent_id'] . '">Historia wizyt</button>';
                                        echo '<button class="btn-view-results" data-patient-id="' . $pacjent['pacjent_id'] . '">Wyniki badań</button>';
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<p class="no-patients">Brak pacjentów w bazie</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Wystaw Wyniki -->
                    <div class="panel-content" id="wystaw-wyniki" style="display: none;">
                        <h2>Wystaw Wyniki</h2>
                        <div class="results-form-container">
                            <form id="resultsForm" class="results-form" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="patientSelect">Wybierz pacjenta:</label>
                                    <select id="patientSelect" name="patient_id" required>
                                        <option value="">-- Wybierz pacjenta --</option>
                                        <?php
                                        // Pobieranie listy pacjentów
                                        $stmt = $conn->prepare("SELECT DISTINCT 
                                            p.id as pacjent_id,
                                            u.imie,
                                            u.nazwisko,
                                            u.pesel
                                            FROM patients p 
                                            JOIN users u ON p.uzytkownik_id = u.id
                                            JOIN visits v ON v.pacjent_id = p.id
                                            JOIN doctors d ON v.lekarz_id = d.id
                                            WHERE d.uzytkownik_id = :user_id
                                            ORDER BY u.nazwisko, u.imie");
                                        $stmt->bindParam(':user_id', $_SESSION['user_id']);
                                        $stmt->execute();
                                        $pacjenci = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($pacjenci as $pacjent) {
                                            echo '<option value="' . $pacjent['pacjent_id'] . '">' . 
                                                 htmlspecialchars($pacjent['nazwisko'] . ' ' . $pacjent['imie'] . 
                                                 ' (PESEL: ' . $pacjent['pesel'] . ')') . 
                                                 '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="testType">Typ badania:</label>
                                    <select id="testType" name="typ_badania" required>
                                        <option value="">-- Wybierz typ badania --</option>
                                        <option value="Morfologia krwi">Morfologia krwi</option>
                                        <option value="Badanie moczu">Badanie moczu</option>
                                        <option value="EKG">EKG</option>
                                        <option value="RTG">RTG</option>
                                        <option value="USG">USG</option>
                                        <option value="Tomografia">Tomografia</option>
                                        <option value="Rezonans magnetyczny">Rezonans magnetyczny</option>
                                        <option value="Inne">Inne</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="testDate">Data badania:</label>
                                    <input type="datetime-local" id="testDate" name="data_badania" required>
                                </div>

                                <div class="form-group">
                                    <label for="testDescription">Opis wyników:</label>
                                    <textarea id="testDescription" name="opis" rows="4" required></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="testFile">Załącz plik z wynikami:</label>
                                    <div class="file-upload">
                                        <input type="file" id="testFile" name="plik_wyniku" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                        <label for="testFile" class="file-label">
                                            <span class="file-icon">📎</span>
                                            <span class="file-text">Wybierz plik</span>
                                        </label>
                                        <span class="file-name">Nie wybrano pliku</span>
                                    </div>
                                    <small class="file-info">Dozwolone formaty: PDF, DOC, DOCX, JPG, PNG (max. 10MB)</small>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn-submit">Wystaw wynik</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Wizyta -->
                    <div class="panel-content" id="wizyta" style="display: none;">
                        <h2>Dodaj wizytę</h2>
                        <div class="visit-form-container">
                            <form id="visitForm" class="visit-form">
                                <div class="form-group">
                                    <label for="visitPatient">Pacjent:</label>
                                    <select name="pacjent_id" id="visitPatient" required>
                                        <option value="">Wybierz pacjenta</option>
                                        <?php
                                        $stmt = $conn->prepare("
                                            SELECT DISTINCT 
                                                p.id, 
                                                u.imie, 
                                                u.nazwisko, 
                                                u.pesel
                                            FROM patients p
                                            JOIN users u ON p.uzytkownik_id = u.id
                                            JOIN visits v ON p.id = v.pacjent_id
                                            JOIN doctors d ON v.lekarz_id = d.id
                                            WHERE d.uzytkownik_id = :user_id
                                            ORDER BY u.nazwisko, u.imie
                                        ");
                                        $stmt->bindParam(':user_id', $_SESSION['user_id']);
                                        $stmt->execute();
                                        while ($row = $stmt->fetch()) {
                                            echo "<option value='{$row['id']}'>{$row['imie']} {$row['nazwisko']} (PESEL: {$row['pesel']})</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="data_wizyty">Data i godzina wizyty:</label>
                                    <input type="datetime-local" name="data_wizyty" id="data_wizyty" required>
                                </div>
                                <div class="form-group">
                                    <label for="typ_wizyty">Typ wizyty:</label>
                                    <select name="typ_wizyty" id="typ_wizyty" required>
                                        <option value="pierwsza">Pierwsza wizyta</option>
                                        <option value="kontrolna">Wizyta kontrolna</option>
                                        <option value="pogotowie">Pogotowie</option>
                                        <option value="szczepienie">Szczepienie</option>
                                        <option value="badanie">Badanie</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="gabinet">Gabinet:</label>
                                    <input type="text" name="gabinet" id="gabinet" required>
                                </div>
                                <div class="form-group">
                                    <label for="opis">Opis:</label>
                                    <textarea name="opis" id="opis" rows="3"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="diagnoza">Diagnoza:</label>
                                    <textarea name="diagnoza" id="diagnoza" rows="3"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="zalecenia">Zalecenia:</label>
                                    <textarea name="zalecenia" id="zalecenia" rows="3"></textarea>
                                </div>
                                <button type="submit" class="btn-submit">Zapisz wizytę</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Kontakt</h3>
                <p>aleja Niepodległości 6</p>
                <p>39-300 Mielec</p>
                <p>tel: (+48) 451 551 819</p>
            </div>
            <div class="footer-section">
                <h3>Godziny przyjęć</h3>
                <p>Poniedziałek - Piątek: 11:00 - 17:00</p>
                <p>Sobota: Zamknięte</p>
                <p>Niedziela: Zamknięte</p>
            </div>
            <div class="footer-section">
                <h3>Obserwuj nas</h3>
                <div class="social-links">
                    <a href="#" target="_blank" title="Facebook"><img src="img/social/facebook.png" alt="Facebook"></a>
                    <a href="#" target="_blank" title="Instagram"><img src="img/social/instagram.png" alt="Instagram"></a>
                    <a href="#" target="_blank" title="Twitter"><img src="img/social/twitter.png" alt="Twitter"></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 Prywatny Szpital im. Coinplex. Wszelkie prawa zastrzeżone.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn-start');
            
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    const visitId = this.dataset.visitId;
                    const button = this;
                    
                    fetch('panel-lekarza.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'visit_id=' + visitId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            button.textContent = 'Wizyta zakończona';
                            button.classList.remove('btn-start');
                            button.classList.add('btn-completed');
                            button.disabled = true;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Wystąpił błąd podczas aktualizacji statusu wizyty');
                    });
                });
            });
        });
    </script>
</body>
</html> 