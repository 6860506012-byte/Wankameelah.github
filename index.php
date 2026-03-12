<?php
// --- 1. ส่วนเชื่อมต่อฐานข้อมูล ---
$host     = getenv('DB_HOST') ?: "wandb-mariadb-yljllb"; 
$username = getenv('DB_USER') ?: "wan";
$password = getenv('DB_PASS') ?: "Wan_2004";
$dbname   = getenv('DB_NAME') ?: "trees_db";

$conn = new mysqli($host, $username, $password);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    $db_status = "❌ Connection Failed: " . $conn->connect_error;
} else {
    // 2. สร้าง DB และ Table อัตโนมัติ (คะแนนข้อ 3)
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

// 3. ส่วนของการจัดการข้อมูล (Add/Delete) - แก้ไขบั๊กเลข 0 โดยการเช็คค่าว่าง
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_val']) && $_POST['add_val'] !== "") {
        $val = (int)$_POST['add_val'];
        $conn->query("INSERT INTO bst_nodes (node_value) VALUES ($val)");
    } elseif (isset($_POST['del_val']) && $_POST['del_val'] !== "") {
        $val = (int)$_POST['del_val'];
        $conn->query("DELETE FROM bst_nodes WHERE node_value = $val");
    }
    header("Location: " . $_SERVER['PHP_SELF']); 
    exit();
}

// 4. ดึงข้อมูลจากฐานข้อมูลมาวาดต้นไม้
$db_nodes = [];
$res = $conn->query("SELECT node_value FROM bst_nodes ORDER BY id ASC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $db_nodes[] = (int)$row['node_value'];
    }
} else {
    // ถ้า DB ว่าง ให้แสดงตัวอย่างตั้งต้น
    $db_nodes = [50, 30, 70, 20, 40, 60, 80]; 
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Binary Tree PHP + MariaDB</title>
    <style>
        /* CSS เดิมของคุณ */
        :root { --bg-color: #fcfcfc; --text-main: #2d3436; --accent-green: #6ab04c; --node-border: #dfe6e9; }
        body { margin: 0; padding: 40px 20px; display: flex; flex-direction: column; align-items: center; background-color: var(--bg-color); font-family: 'Tahoma', sans-serif; }
        .container { width: 100%; max-width: 800px; background: #ffffff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); text-align: center; }
        h1 { font-weight: 300; color: #ff7675; }
        .input-group { margin-bottom: 30px; display: flex; justify-content: center; gap: 10px; }
        input { padding: 10px; border: 1px solid var(--node-border); border-radius: 8px; width: 70px; text-align: center; }
        button { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; }
        .btn-add { background-color: #ff7675; color: white; }
        .btn-clear { background-color: #dfe6e9; color: #636e72; }
        canvas { width: 100%; height: auto; }
        .results-section { margin-top: 30px; text-align: left; border-top: 1px solid #eee; padding-top: 20px; }
        .result-item b { color: var(--accent-green); }
    </style>
</head>
<body>

<div class="container">
    <h1>🎄 Binary Tree PHP 🎄</h1>
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

<script>
    class Node { constructor(v) { this.val = v; this.left = null; this.right = null; } }
    let root = null;
    const canvas = document.getElementById('treeCanvas');
    const ctx = canvas.getContext('2d');

    // ดึงข้อมูลที่ PHP ดึงจาก DB มาเตรียมไว้ใน JS
    const dbValues = <?php echo json_encode($nodes_from_db); ?>;

    window.onload = () => {
        dbValues.forEach(v => buildLocally(v));
        render();
    };

    function buildLocally(v) {
        if (!root) root = new Node(v);
        else insertNode(root, v);
    }

    function insertNode(node, v) {
        if (v < node.val) {
            if (!node.left) node.left = new Node(v); else insertNode(node.left, v);
        } else {
            if (!node.right) node.right = new Node(v); else insertNode(node.right, v);
        }
    }

    async function addNode() {
        const val = document.getElementById('nodeInput').value;
        if (val === "") return;

        // ส่งค่าไปบันทึกใน PHP (Database) แบบ AJAX
        let formData = new FormData();
        formData.append('action', 'add');
        formData.append('value', val);
        
        await fetch('index.php', { method: 'POST', body: formData });
        
        buildLocally(parseInt(val));
        document.getElementById('nodeInput').value = '';
        render();
    }

    async function resetTree() {
        if(!confirm("ลบข้อมูลใน Database?")) return;
        let formData = new FormData();
        formData.append('action', 'clear');
        await fetch('index.php', { method: 'POST', body: formData });
        root = null;
        render();
    }

    function render() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (root) draw(root, canvas.width / 2, 40, 160);
        updateTraversalText();
    }

    function draw(node, x, y, space) {
        ctx.strokeStyle = "#dfe6e9";
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
