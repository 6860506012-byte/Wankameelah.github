<?php
// --- 1. ส่วนเชื่อมต่อฐานข้อมูล ---
$host     = getenv('DB_HOST') ?: "wandb-mariadb-yljllb"; 
$username = getenv('DB_USER') ?: "wan";
$password = getenv('DB_PASS') ?: "Wan_2004";
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
    <title>Binary Tree PHP + MariaDB</title>
    <style>
        :root { --bg-color: #fcfcfc; --text-main: #2d3436; --accent-green: #6ab04c; --node-border: #dfe6e9; }
        body { margin: 0; padding: 40px 20px; display: flex; flex-direction: column; align-items: center; background-color: var(--bg-color); font-family: 'Tahoma', sans-serif; }
        .container { width: 100%; max-width: 800px; background: #ffffff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); text-align: center; border: 1px solid #eee; }
        h1 { font-weight: 300; color: #ff7675; margin-bottom: 5px; }
        .db-status { font-size: 12px; color: #95a5a6; margin-bottom: 20px; }
        .input-group { margin-bottom: 30px; display: flex; justify-content: center; gap: 10px; }
        input { padding: 10px; border: 1px solid var(--node-border); border-radius: 8px; width: 80px; text-align: center; outline: none; }
        button { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn-add { background-color: #ff7675; color: white; }
        .btn-add:hover { background-color: #ff5e57; transform: scale(1.05); }
        .btn-clear { background-color: #dfe6e9; color: #636e72; }
        canvas { width: 100%; height: auto; border-bottom: 1px solid #f5f5f5; }
        .results-section { margin-top: 30px; text-align: left; padding-top: 10px; }
        .result-item { margin-bottom: 10px; font-size: 14px; }
        .result-item b { color: var(--accent-green); display: inline-block; width: 100px; }
    </style>
</head>
<body>

<div class="container">
    <h1>🎄 Binary Tree 🎄</h1>
    <div class="db-status"><?php echo $db_status; ?></div>
    
    <div class="input-group">
        <input type="number" id="nodeInput" placeholder="ใส่เลข">
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

<script>
    class Node { constructor(v) { this.val = v; this.left = null; this.right = null; } }
    let root = null;
    const canvas = document.getElementById('treeCanvas');
    const ctx = canvas.getContext('2d');

    // แก้ไข: ใช้ชื่อตัวแปร $db_nodes ให้ตรงกับ PHP
    const dbValues = <?php echo json_encode($db_nodes); ?>;

    window.onload = () => {
        if(dbValues.length > 0) {
            dbValues.forEach(v => buildLocally(v));
            render();
        }
    };

    function buildLocally(v) {
        if (!root) root = new Node(v);
        else insertRecursive(root, v);
    }

    function insertRecursive(node, v) {
        if (v < node.val) {
            if (!node.left) node.left = new Node(v); else insertRecursive(node.left, v);
        } else if (v > node.val) {
            if (!node.right) node.right = new Node(v); else insertRecursive(node.right, v);
        }
    }

    async function addNode() {
        const valInput = document.getElementById('nodeInput');
        const val = valInput.value;
        if (val === "") return;

        let formData = new FormData();
        formData.append('action', 'add');
        formData.append('value', val);
        
        // ส่งไปที่ไฟล์ตัวเอง
        await fetch(window.location.href, { method: 'POST', body: formData });
        
        buildLocally(parseInt(val));
        valInput.value = '';
        render();
    }

    async function resetTree() {
        if(!confirm("ต้องการล้างข้อมูลทั้งหมดใช่หรือไม่?")) return;
        let formData = new FormData();
        formData.append('action', 'clear');
        await fetch(window.location.href, { method: 'POST', body: formData });
        root = null;
        render();
    }

    function render() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (root) draw(root, canvas.width / 2, 40, 180);
        updateTraversalText();
    }

    function draw(node, x, y, space) {
        ctx.lineWidth = 2;
        ctx.strokeStyle = "#dfe6e9";
        if (node.left) {
            ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x - space, y + 70); ctx.stroke();
            draw(node.left, x - space, y + 70, space / 2);
        }
        if (node.right) {
            ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + space, y + 70); ctx.stroke();
            draw(node.right, x + space, y + 70, space / 2);
        }
        
        ctx.beginPath(); ctx.arc(x, y, 18, 0, Math.PI * 2);
        ctx.fillStyle = "white"; ctx.fill();
        ctx.strokeStyle = "#ff7675"; ctx.stroke();
        
        ctx.fillStyle = "#2d3436"; ctx.font = "bold 12px Arial"; ctx.textAlign = "center";
        ctx.fillText(node.val, x, y + 5);
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
