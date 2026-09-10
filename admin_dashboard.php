<?php
session_start();

// Simple Admin Authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "simple-forms";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$sql = "SELECT * FROM submissions ORDER BY submitted_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-hover: #4f46e5;
            --bg-color: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 2rem;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        h1 { font-weight: 700; color: var(--text-main); }
        .btn-logout {
            padding: 0.6rem 1.2rem;
            background: #ef4444;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .table-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th, td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.875rem;
        }
        th {
            background: #f1f5f9;
            font-weight: 600;
            color: var(--text-main);
        }
        tr:hover { background-color: #f8fafc; }

        /* Mobile Responsive Table */
        @media (max-width: 600px) {
            .hide-mobile {
                display: none;
            }
            .container {
                padding: 1rem;
            }
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }

        .btn-view {
            padding: 0.4rem 0.8rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.75rem;
            transition: background 0.2s;
        }
        .btn-view:hover { background: var(--primary-hover); }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: flex-start;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
            overflow-y: auto;
            padding: 2rem 1rem;
        }
        .modal-card {
            background: white;
            width: 90%;
            max-width: 500px;
            border-radius: 24px;
            padding: 2.5rem;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin: auto;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            border: none;
            background: none;
        }
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-main);
            text-align: center;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .info-item {
            margin-bottom: 1rem;
        }
        .info-label {
            display: block;
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-size: 0.95rem;
            color: var(--text-main);
            font-weight: 500;
        }
        .full-width {
            grid-column: span 2;
        }
        .mood-section {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--border-color);
        }
        .mood-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        .mood-card {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 10px;
            text-align: center;
        }
        .mood-card img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .mood-card b {
            display: block;
            font-size: 0.8rem;
            text-transform: capitalize;
            color: var(--primary-color);
            margin-bottom: 4px;
        }
        .mood-card span {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: block;
            line-height: 1.4;
        }
        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .full-width {
                grid-column: span 1;
            }
            .mood-grid {
                grid-template-columns: 1fr;
            }
            .modal-card {
                padding: 1.5rem;
                width: 95%;
            }
            .modal-title {
                font-size: 1.25rem;
            }
        }
        .selfie-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .selfie-img {
            width: 150px;
            height: 150px;
            max-width: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid var(--primary-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Submissions Dashboard</h1>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="hide-mobile">ID</th>
                        <th>Name</th>
                        <th class="hide-mobile">Email</th>
                        <th class="hide-mobile">Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            // Prepare data for JS modal
                            $userData = htmlspecialchars(json_encode($row));
                            echo "<tr>
                                <td class='hide-mobile'>" . $row["id"] . "</td>
                                <td><b>" . htmlspecialchars($row["name"]) . "</b></td>
                                <td class='hide-mobile'>" . htmlspecialchars($row["email"]) . "</td>
                                <td class='hide-mobile'>" . $row["submitted_at"] . "</td>
                                <td>
                                    <button class='btn-view' onclick='viewUser(" . $userData . ")'>View Info</button>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center;'>No submissions found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for User Details -->
    <div id="userModal" class="modal-overlay" onclick="closeModal(event)">
        <div class="modal-card" onclick="event.stopPropagation()">
            <button class="close-modal" onclick="closeModal()">×</button>
            <div class="modal-title" id="m-name">User Details</div>

            <div class="selfie-container">
                <img src="" id="m-selfie" class="selfie-img" style="display:none;" alt="User Selfie">
            </div>

            <div class="info-grid">
                <div class="info-item full-width">
                    <span class="info-label">Email Address</span>
                    <span class="info-value" id="m-email"></span>
                </div>
                <div class="info-item full-width">
                    <span class="info-label">Home Address</span>
                    <span class="info-value" id="m-address"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Birthday</span>
                    <span class="info-value" id="m-bday"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone Number</span>
                    <span class="info-value" id="m-phone"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">SIM Network</span>
                    <span class="info-value" id="m-sim"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Favorite Food</span>
                    <span class="info-value" id="m-food"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Favorite Drink</span>
                    <span class="info-value" id="m-drink"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Hobby</span>
                    <span class="info-value" id="m-hobby"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Favorite Color</span>
                    <span class="info-value" id="m-color"></span>
                </div>
                <div class="info-item full-width">
                    <span class="info-label">Superpower Answer</span>
                    <span class="info-value" id="m-power"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Favorite Animal</span>
                    <span class="info-value" id="m-animal"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Weird Food Combo</span>
                    <span class="info-value" id="m-weird"></span>
                </div>
                <div class="info-item full-width">
                    <span class="info-label">Biggest Dream</span>
                    <span class="info-value" id="m-dream"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Role Model</span>
                    <span class="info-value" id="m-role"></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Future Self</span>
                    <span class="info-value" id="m-future"></span>
                </div>
                <div class="info-item full-width">
                    <span class="info-label">World Change</span>
                    <span class="info-value" id="m-change"></span>
                </div>
            </div>
            <div class="mood-section">
                <div class="modal-title" style="font-size: 1.2rem; margin-bottom: 1rem;">Mood Photos</div>
                <div id="mood-grid" class="mood-grid">
                    <!-- Moods will be loaded here -->
                </div>
            </div>
        </div>
    </div>


    <script>
        async function viewUser(user) {
            document.getElementById('m-name').innerText = user.name;
            document.getElementById('m-email').innerText = user.email || 'N/A';
            document.getElementById('m-address').innerText = user.address || 'N/A';
            document.getElementById('m-bday').innerText = user.birthday || 'N/A';
            document.getElementById('m-phone').innerText = user.phone || 'N/A';
            document.getElementById('m-sim').innerText = user.sim_card || 'N/A';
            document.getElementById('m-food').innerText = user.fav_food || 'N/A';
            document.getElementById('m-drink').innerText = user.fav_drink || 'N/A';
            document.getElementById('m-hobby').innerText = user.hobby || 'N/A';
            document.getElementById('m-color').innerText = user.fav_color || 'N/A';
            document.getElementById('m-power').innerText = user.fun_question || 'N/A';
            document.getElementById('m-animal').innerText = user.fav_animal || 'N/A';
            document.getElementById('m-weird').innerText = user.weird_food || 'N/A';
            document.getElementById('m-dream').innerText = user.biggest_dream || 'N/A';
            document.getElementById('m-role').innerText = user.role_model || 'N/A';
            document.getElementById('m-future').innerText = user.future_self || 'N/A';
            document.getElementById('m-change').innerText = user.world_change || 'N/A';

            const selfieImg = document.getElementById('m-selfie');
            if (user.selfie) {
                selfieImg.src = user.selfie;
                selfieImg.style.display = 'inline-block';
            } else {
                selfieImg.style.display = 'none';
            }

            // Fetch and load mood photos
            const moodGrid = document.getElementById('mood-grid');
            moodGrid.innerHTML = 'Loading moods...';
            try {
                const response = await fetch('get_moods.php?id=' + user.id);
                const moods = await response.json();
                moodGrid.innerHTML = '';
                if (moods.length > 0) {
                    moods.forEach(mood => {
                        moodGrid.innerHTML += `
                            <div class="mood-card">
                                <img src="${mood.photo_path}" alt="${mood.mood}">
                                <b>${mood.mood}</b>
                                <span>${mood.reason || 'No reason provided'}</span>
                            </div>`;
                    });
                } else {
                    moodGrid.innerHTML = '<p style="grid-column: span 2; text-align: center; color: var(--text-muted);">No mood photos uploaded.</p>';
                }
            } catch (err) {
                moodGrid.innerHTML = '<p style="grid-column: span 2; text-align: center; color: red;">Error loading moods.</p>';
            }

            document.getElementById('userModal').style.display = 'flex';
        }

        function closeModal(e) {
            document.getElementById('userModal').style.display = 'none';
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
