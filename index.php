<?php
// --- 1. ส่วนเชื่อมต่อฐานข้อมูล ---
$host     = getenv('DB_HOST') ?: "wandb-mariadb-yljllb"; 
$username = getenv('DB_USER') ?: "wan";
$password = getenv('DB_PASS') ?: "Wan_2007";
$dbname   = getenv('DB_NAME') ?: "trees_db";

$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    $db_status = "❌ Connection Failed: " . $conn->connect_error;
} else {
    $conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
    $conn->select_db($dbname);
    
    $sql_create_table = "CREATE TABLE IF NOT EXISTS bst_nodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        node_value INT NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql_create_table);
    $db_status = "✅ Connected to MariaDB ($host)";
}

// 3. ส่วนจัดการข้อมูล (ปรับให้ตรงกับ AJAX ที่ JS ส่งมา)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' && $_POST['value'] !== "") {
            $val = (int)$_POST['value'];
            $conn->query("INSERT INTO bst_nodes (node_value) VALUES ($val)");
        } elseif ($_POST['action'] === 'clear') {
            $conn->query("TRUNCATE TABLE bst_nodes");
        }
        exit(); // จบการทำงานสำหรับ AJAX
    }
}

// 4. ดึงข้อมูล
$db_nodes = [];
$res = $conn->query("SELECT node_value FROM bst_nodes ORDER BY id ASC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $db_nodes[] = (int)$row['node_value'];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minimal Binary Tree with Guide</title>
    <style>
        /* --- Minimal CSS --- */
        :root {
            --bg-color: #fcfcfc;
            --text-main: #2d3436;
            --accent-green: #6ab04c;
            --node-border: #dfe6e9;
        }

        body {
            margin: 0;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: var(--bg-color);
            font-family: 'Tahoma', sans-serif;
            color: var(--text-main);
        }

        .container {
            width: 100%;
            max-width: 800px;
            background: #ffffff;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
            text-align: center;
        }

        h1 { font-weight: 300; font-size: 1.5rem; margin-bottom: 25px; color: #ff7675; }

        .input-group { margin-bottom: 30px; display: flex; justify-content: center; gap: 10px; }

        input {
            padding: 10px;
            border: 1px solid var(--node-border);
            border-radius: 8px;
            width: 70px;
            text-align: center;
            outline: none;
        }

        button {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: 0.2s;
        }

        .btn-add { background-color: #ff7675; color: white; }
        .btn-clear { background-color: #dfe6e9; color: #636e72; }
        button:hover { opacity: 0.8; }

        canvas { width: 100%; height: auto; margin-top: 20px; }

        /* --- ส่วนที่เพิ่ม: Traversal Results & Guide --- */
        .results-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: left;
        }

        .result-item { margin-bottom: 10px; font-size: 0.95rem; }
        .result-item b { color: var(--accent-green); margin-right: 10px; }

        .guide-section {
            width: 100%;
            max-width: 800px;
            margin-top: 40px;
            background: #f9fff9;
            padding: 25px;
            border-radius: 20px;
            border: 1px solid #e1f0e1;
        }

        .guide-title {
            color: var(--accent-green);
            font-size: 1.2rem;
            margin-bottom: 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
        }

        .guide-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .guide-item { display: flex; align-items: flex-start; gap: 12px; }
        .icon-circle {
            width: 32px; height: 32px;
            background: #badc58;
            border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
            flex-shrink: 0; color: white; font-size: 0.8rem;
        }

        .guide-text b { display: block; color: #2d3436; margin-bottom: 2px; }
        .guide-text span { font-size: 0.85rem; color: #636e72; }
    </style>
</head>
<body>

<div class="container">
    <h1> 🎄Binary Tree🎄</h1>
    
    <div class="input-group">
        <input type="number" id="nodeInput" placeholder="เลข">
        <button class="btn-add" onclick="addNode()">ปลูก Node</button>
        <button class="btn-clear" onclick="resetTree()">ล้างสวน</button>
    </div>

    <canvas id="treeCanvas" width="800" height="400"></canvas>

    <div class="results-section">
        <div class="result-item"><b>Preorder:</b> <span id="preText">-</span></div>
        <div class="result-item"><b>Inorder:</b> <span id="inText">-</span></div>
        <div class="result-item"><b>Postorder:</b> <span id="postText">-</span></div>
    </div>
</div>

<div class="guide-section">
    <div class="guide-title">หลักในการ Traversal</div>
    <div class="guide-grid">
        <div class="guide-item">
            <div class="icon-circle">⇄</div>
            <div class="guide-text">
                <b>Preorder</b>
                <span>Root → Left → Right</span>
            </div>
        </div>
        <div class="guide-item">
            <div class="icon-circle" style="background:#badc5899"></div>
            <div class="guide-text">
                <b>Inorder</b>
                <span>Left → Root → Right</span>
            </div>
        </div>
        <div class="guide-item">
            <div class="icon-circle" style="background:#badc5899"></div>
            <div class="guide-text">
                <b>Postorder</b>
                <span>Left → Right → Root</span>
            </div>
        </div>
    </div>
</div>

<script>
    class Node {
        constructor(v) { this.val = v; this.left = null; this.right = null; }
    }

    let root = null;
    const canvas = document.getElementById('treeCanvas');
    const ctx = canvas.getContext('2d');

    function addNode() {
        const val = parseInt(document.getElementById('nodeInput').value);
        if (isNaN(val)) return;
        if (!root) root = new Node(val); else insertNode(root, val);
        document.getElementById('nodeInput').value = '';
        render();
    }

    function insertNode(node, v) {
        if (v < node.val) {
            if (!node.left) node.left = new Node(v); else insertNode(node.left, v);
        } else {
            if (!node.right) node.right = new Node(v); else insertNode(node.right, v);
        }
    }

    function render() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (root) draw(root, canvas.width / 2, 40, 160);
        updateTraversalText();
    }

    function draw(node, x, y, space) {
        ctx.strokeStyle = "#dfe6e9"; ctx.lineWidth = 1;
        if (node.left) {
            ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x - space, y + 70); ctx.stroke();
            draw(node.left, x - space, y + 70, space / 1.8);
        }
        if (node.right) {
            ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + space, y + 70); ctx.stroke();
            draw(node.right, x + space, y + 70, space / 1.8);
        }
        ctx.beginPath(); ctx.arc(x, y, 18, 0, Math.PI * 2);
        ctx.fillStyle = "white"; ctx.fill();
        ctx.strokeStyle = "#ff7675"; ctx.stroke();
        ctx.fillStyle = "#2d3436"; ctx.font = "12px Arial"; ctx.textAlign = "center";
        ctx.fillText(node.val, x, y + 5);
    }

    // Traversal Logic
    const getPre = (n, r=[]) => { if(n){ r.push(n.val); getPre(n.left, r); getPre(n.right, r); } return r; };
    const getIn = (n, r=[]) => { if(n){ getIn(n.left, r); r.push(n.val); getIn(n.right, r); } return r; };
    const getPost = (n, r=[]) => { if(n){ getPost(n.left, r); getPost(n.right, r); r.push(n.val); } return r; };

    function updateTraversalText() {
        document.getElementById('preText').innerText = getPre(root).join(' → ') || '-';
        document.getElementById('inText').innerText = getIn(root).join(' → ') || '-';
        document.getElementById('postText').innerText = getPost(root).join(' → ') || '-';
    }

    function resetTree() { root = null; render(); }
</script>

</body>
</html>
