<?php
$old = $old ?? [];
$errors = $errors ?? [];
$success = $success ?? false;

$oldValue = static fn(string $key): string => htmlspecialchars((string)($old[$key] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/style/app.css">
    <link rel="stylesheet" href="/style/register.css">
    <title>ลงทะเบียน</title>
</head>

<body class="register-page min-h-screen">

    <header>
        <div class="header-inner">
            <div class="logo">
                <a href="/">
                    <img src="/mylogo1.png" alt="Event Logo">
                </a>
            </div>
            <div class="nav">
                <a class="login" href="/home">หน้าหลัก</a>
                <a class="register" href="/login">เข้าสู่ระบบ</a>
            </div>
        </div>
    </header>

    <div class="wrapper">
        <div class="card">
            <div class="left"></div>

            <div class="right">
                <h1 class="text-3xl font-bold">สมัครสมาชิก</h1>
                <div class="mt-2 text-sm text-slate-600">
                    กรอกข้อมูลให้ครบถ้วนเพื่อสร้างบัญชี
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        สมัครสมาชิกสำเร็จแล้ว
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="form" onsubmit="return confirmSubmission()">
                    <div class="grid2">
                        <div class="span2">
                            <label>ชื่อ</label>
                            <input type="text" name="name" value="<?= $oldValue('name') ?>" required>
                        </div>

                        <div>
                            <label>อายุ</label>
                            <input type="number" name="age" value="<?= $oldValue('age') ?>" min="1" required>
                        </div>

                        <div>
                            <label for="gender">เพศ</label>
                            <select id="gender" name="gender" required>
                                <option value="">-- เลือกเพศ --</option>
                                <option value="male"   <?= ($old['gender'] ?? '') === 'male' ? 'selected' : '' ?>>ชาย</option>
                                <option value="female" <?= ($old['gender'] ?? '') === 'female' ? 'selected' : '' ?>>หญิง</option>
                                <option value="other"  <?= ($old['gender'] ?? '') === 'other' ? 'selected' : '' ?>>อื่น ๆ</option>
                            </select>
                        </div>

                        <div class="span2">
                            <label for="career">อาชีพ</label>
                            <select id="career" name="career" required>
                                <option value="">-- เลือกอาชีพ --</option>
                                <option value="student"            <?= ($old['career'] ?? '') === 'student' ? 'selected' : '' ?>>นักเรียน/นักศึกษา</option>
                                <option value="employee"           <?= ($old['career'] ?? '') === 'employee' ? 'selected' : '' ?>>พนักงานบริษัท</option>
                                <option value="business_owner"     <?= ($old['career'] ?? '') === 'business_owner' ? 'selected' : '' ?>>เจ้าของกิจการ</option>
                                <option value="freelancer"         <?= ($old['career'] ?? '') === 'freelancer' ? 'selected' : '' ?>>ฟรีแลนซ์</option>
                                <option value="government_officer" <?= ($old['career'] ?? '') === 'government_officer' ? 'selected' : '' ?>>ข้าราชการ/รัฐวิสาหกิจ</option>
                                <option value="other"              <?= ($old['career'] ?? '') === 'other' ? 'selected' : '' ?>>อื่น ๆ</option>
                            </select>
                        </div>

                        <div class="span2">
                            <label>อีเมล</label>
                            <input type="email" name="email" value="<?= $oldValue('email') ?>" required>
                        </div>

                        <div class="span2">
                            <label>รหัสผ่าน</label>
                            <input type="password" name="password" required>
                        </div>

                        <div class="span2">
                            <label>ยืนยันรหัสผ่าน</label>
                            <input type="password" name="confirm_password" required>
                        </div>

                        <div class="span2">
                            <button type="submit">สมัครสมาชิก</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        function confirmSubmission() {
            return confirm("ต้องการลงทะเบียนจริงหรือไม่ ?");
        }
    </script>
</body>
</html>
