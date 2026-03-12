<?php
// --- 1. ส่วนเชื่อมต่อฐานข้อมูล ---
$host     = getenv('DB_HOST') ?: "wandb-mariadb-yljllb"; 
$username = getenv('DB_USER') ?: "wan";
$password = getenv('DB_PASS') ?: "Wan_2007";
$dbname   = getenv('DB_NAME') ?: "trees_db";

$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    $db_status = "❌ Connection Failed";
} else {
    $conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
    $conn->select_db($dbname);
    
    $sql_create_table = "CREATE TABLE IF NOT EXISTS bst_nodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        node_value INT NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql_create_table);
    $db_status = "✅ Connected to MariaDB";
}

// --- 2. ส่วนจัดการข้อมูล (AJAX Handling) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'add' && isset($_POST['value'])) {
        $val = (int)$_POST['value'];
        // ใช้ INSERT IGNORE เพื่อป้องกัน Error กรณีค่าซ้ำ (Unique Constraint)
        $stmt = $conn->prepare("INSERT IGNORE INTO bst_nodes (node_value) VALUES (?)");
        $stmt->bind_param("i", $val);
        $stmt->execute();
        echo json_encode(["status" => "success"]);
    } elseif ($_POST['action'] === 'clear') {
        $conn->query("TRUNCATE TABLE bst_nodes");
        echo json_encode(["status" => "cleared"]);
    }
    exit(); // หยุดการทำงานเพื่อไม่ให้แสดง HTML ใน AJAX Response
}

// --- 3. ดึงข้อมูลมาแสดงผลตอนโหลดหน้า ---
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
    <title>Binary Search Tree Manager</title>
    <style>
        :root {
            --bg-color: #f8f9fa;
            --text-main: #2d3436;
            --accent-pink: #ff7675;
            --accent-green: #6ab04c;
            --node-border: #dfe6e9;
        }

        body {
            margin: 0; padding: 40px 20px;
            display: flex; flex-direction: column; align-items: center;
            background-color: var(--bg-color);
            font-family: 'Segoe UI', Tahoma, sans-serif;
            color: var(--text-main);
        }

        .container {
            width: 100%; max-width: 850px;
            background: #ffffff; padding: 30px;
            border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            text-align: center;
        }

        .db-badge {
            font-size: 0.75rem; padding: 5px 12px;
            border-radius: 15px; background: #eee; margin-bottom: 10px; display: inline-block;
        }

        h1 { font-weight: 600; font-size: 1.8rem; margin-bottom: 25px; color: var(--accent-pink); }

        .input-group { margin-bottom: 30px; display: flex; justify-content: center; gap: 10px; }

        input {
            padding: 10px; border: 2px solid var(--node-border);
            border-radius: 10px; width: 80px; text-align: center; font-size: 1rem;
        }

        button {
            padding: 10px 20px; border-radius: 10px; border: none;
            cursor: pointer; font-weight: bold; transition: 0.2s;
        }

        .btn-add { background-color: var(--accent-pink); color: white; }
        .btn-clear { background-color: #dfe6e9; color: #636e72; }
        button:hover { transform: translateY(-2px); opacity: 0.9; }

        canvas { width: 100%; height: auto; background: #fff; border-radius: 15px; }

        .results-section {
            margin-top: 30px; padding-top: 20px;
            border-top: 1px solid #eee; text-align: left;
        }

        .result-item { margin-bottom: 8px; font-size: 0.95rem; }
        .result-item b { color: var(--accent-green); width: 100px; display: inline-block; }

        .guide-section {
            width: 100%; max-width: 850px; margin-top: 30px;
            background: #f1f8e9; padding: 20px; border-radius: 15px;
        }

        .guide-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .guide-text b { color: #333; font-size: 0.9rem; }
        .guide-text span { font-size: 0.8rem; color: #666; }
    </style>
</head>
<body>

<div class="container">
    <div class="db-badge"><?php echo $db_status; ?></div>
    <h1>🎄 Binary Search Tree Visualizer</h1>
    
    <div class="input-group">
        <input type="number" id="nodeInput" placeholder="ค่า" onkeyup="if(event.key==='Enter') addNode()">
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
    <div class="guide-grid">
        <div class="guide-text"><b>Preorder</b><br><span>Root → Left → Right</span></div>
        <div class="guide-text"><b>Inorder</b><br><span>Left → Root → Right (Sorted)</span></div>
        <div class="guide-text"><b>Postorder</b><br><span>Left → Right → Root</span></div>
    </div>
</div>

<script>
    class Node {
        constructor(v) { this.val = v; this.left = null; this.right = null; }
    }

    let root = null;
    const canvas = document.getElementById('treeCanvas');
    const ctx = canvas.getContext('2d');

    // โหลดข้อมูลเดิมจาก PHP เมื่อหน้าจอพร้อม
    window.onload = () => {
        const initialData = <?php echo json_encode($db_nodes); ?>;
        initialData.forEach(val => {
            buildLocalTree(val);
        });
        render();
    };

    function buildLocalTree(val) {
        if (!root) root = new Node(val); 
        else insertNodeLogic(root, val);
    }

    function insertNodeLogic(node, v) {
        if (v < node.val) {
            if (!node.left) node.left = new Node(v); else insertNodeLogic(node.left, v);
        } else if (v > node.val) {
            if (!node.right) node.right = new Node(v); else insertNodeLogic(node.right, v);
        }
    }

    // ฟังก์ชันส่งข้อมูลไปเซฟ (AJAX)
    async function addNode() {
        const input = document.getElementById('nodeInput');
        const val = parseInt(input.value);
        if (isNaN(val)) return;

        let formData = new FormData();
        formData.append('action', 'add');
        formData.append('value', val);

        try {
            await fetch(window.location.href, { method: 'POST', body: formData });
            buildLocalTree(val);
            input.value = '';
            render();
        } catch (e) { console.error("Save failed", e); }
    }

    async function resetTree() {
        if(!confirm("ต้องการล้างข้อมูลทั้งหมดใช่หรือไม่?")) return;
        
        let formData = new FormData();
        formData.append('action', 'clear');
        
        try {
            await fetch(window.location.href, { method: 'POST', body: formData });
            root = null;
            render();
        } catch (e) { console.error("Clear failed", e); }
    }

    function render() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (root) draw(root, canvas.width / 2, 40, 180);
        updateTraversalText();
    }

    function draw(node, x, y, space) {
        if (node.left) {
            ctx.beginPath(); ctx.strokeStyle = "#ccc";
            ctx.moveTo(x, y); ctx.lineTo(x - space, y + 60); ctx.stroke();
            draw(node.left, x - space, y + 60, space / 2);
        }
        if (node.right) {
            ctx.beginPath(); ctx.strokeStyle = "#ccc";
            ctx.moveTo(x, y); ctx.lineTo(x + space, y + 60); ctx.stroke();
            draw(node.right, x + space, y + 60, space / 2);
        }
        
        // วาดวงกลม Node
        ctx.beginPath();
        ctx.arc(x, y, 18, 0, Math.PI * 2);
        ctx.fillStyle = "white"; ctx.fill();
        ctx.strokeStyle = "#ff7675"; ctx.lineWidth = 2; ctx.stroke();
        
        // วาดตัวเลข
        ctx.fillStyle = "#2d3436"; ctx.font = "bold 13px Arial";
        ctx.textAlign = "center"; ctx.fillText(node.val, x, y + 5);
    }

    const getPre = (n, r=[]) => { if(n){ r.push(n.val); getPre(n.left, r); getPre(n.right, r); } return r; };
    const getIn = (n, r=[]) => { if(n){ getIn(n.left, r); r.push(n.val); getIn(n.right, r); } return r; };
    const getPost = (n, r=[]) => { if(n){ getPost(n.left, r); getPost(n.right, r); r.push(n.val); } return r; };

    function updateTraversalText() {
        document.getElementById('preText').innerText = getPre(root).join(' → ') || '-';
        document.getElementById('inText').innerText = getIn(root).join(' → ') || '-';
        document.getElementById('postText').innerText = getPost(root).join(' → ') || '-';
    }
</script>

</body>
</html>
