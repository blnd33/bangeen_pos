<?php
require_once __DIR__ . '/includes/config.php';
if (is_logged_in()) { header('Location: /bangeen_pos/dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if ($u && $p) {
        $s = DB::get()->prepare("SELECT id,username,password_hash,full_name_ar,full_name_en,role FROM users WHERE username=? AND is_active=1");
        $s->execute([$u]);
        $user = $s->fetch();
        if ($user && password_verify($p, $user['password_hash'])) {
            $_SESSION['pos_user_id'] = $user['id'];
            $_SESSION['pos_username'] = LANG==='ar' ? ($user['full_name_ar']?:$user['username']) : ($user['full_name_en']?:$user['username']);
            $_SESSION['pos_role'] = $user['role'];
            header('Location: /bangeen_pos/dashboard.php'); exit;
        } else { $error = LANG==='ar' ? 'اسم المستخدم أو كلمة المرور غير صحيحة' : 'Invalid username or password.'; }
    } else { $error = LANG==='ar' ? 'يرجى إدخال بيانات الدخول' : 'Please enter your credentials.'; }
}
?>
<!DOCTYPE html>
<html lang="<?= LANG ?>" dir="<?= DIR ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= t('login') ?> — <?= LANG==='ar'?'بهنگین کریستال':'Bangeen Crystal' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&family=Raleway:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--brand:#C4922A;--cream:#F5F0EB;--cream2:#EDE8E3;--dark:#1A1008;--text:#1C1410;--muted:#9C8A7A;--border:#DDD6CE;--font:<?= LANG==='ar'?"'Tajawal',sans-serif":"'Raleway',sans-serif" ?>}
body{background:var(--cream);font-family:var(--font);color:var(--text);min-height:100vh;display:flex;direction:<?= DIR ?>}

.login-split{display:flex;width:100%;min-height:100vh}

/* Left panel — brand */
.brand-panel{
  flex:1;background:var(--dark);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:3rem;position:relative;overflow:hidden;
}
.brand-panel::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse at 30% 50%, rgba(196,146,42,.18) 0%, transparent 65%);
}
.brand-panel-inner{position:relative;z-index:1;text-align:center}

.big-logo{width:130px;height:130px;margin:0 auto 2rem}
.big-logo svg{width:100%;height:100%}

.brand-name-ar{font-family:'Tajawal',sans-serif;font-weight:800;font-size:2.6rem;color:#fff;line-height:1.1}
.brand-name-en{font-family:'Raleway',sans-serif;font-weight:700;font-size:1.1rem;color:rgba(255,255,255,.4);letter-spacing:.08em;margin-top:.3rem}
.brand-tagline{
  margin-top:1.5rem;
  font-size:.88rem;color:rgba(255,255,255,.35);
  font-family:<?= LANG==='ar'?"'Tajawal'":"'Raleway'" ?>,sans-serif;
}

/* Decorative grid */
.deco-grid{
  position:absolute;inset:0;
  background-image:linear-gradient(rgba(196,146,42,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(196,146,42,.06) 1px,transparent 1px);
  background-size:32px 32px;
}

/* Right panel — form */
.form-panel{
  width:420px;min-width:380px;background:var(--cream2);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:3rem 2.5rem;
}

.form-box{width:100%}
.form-heading{font-size:1.4rem;font-weight:800;margin-bottom:.3rem}
.form-sub{font-size:.85rem;color:var(--muted);margin-bottom:2rem}

.form-group{margin-bottom:1.1rem;display:flex;flex-direction:column;gap:.38rem}
label{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;font-family:'Raleway',sans-serif}
input{
  background:#fff;border:1px solid var(--border);border-radius:9px;
  padding:.68rem .9rem;color:var(--text);font-size:.95rem;font-family:var(--font);
  outline:none;transition:border-color .15s,box-shadow .15s;width:100%;
}
input:focus{border-color:var(--brand);box-shadow:0 0 0 2.5px rgba(196,146,42,.15)}

.btn-login{
  width:100%;padding:.78rem;
  background:var(--brand);border:none;border-radius:9px;
  color:#fff;font-size:1rem;font-weight:700;font-family:var(--font);
  cursor:pointer;letter-spacing:.03em;transition:all .18s;margin-top:.5rem;
}
.btn-login:hover{background:#9e7520;box-shadow:0 6px 18px rgba(196,146,42,.35)}

.error-msg{
  background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.25);
  border-radius:8px;padding:.65rem .9rem;color:#b91c1c;
  font-size:.85rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;
}

.hint-box{
  margin-top:1.5rem;padding:1rem;background:#fff;border:1px solid var(--border);
  border-radius:10px;font-size:.8rem;color:var(--muted);text-align:center;
}
.hint-box code{background:var(--cream);padding:.1rem .35rem;border-radius:4px;color:var(--brand);font-family:monospace}

.lang-toggle{margin-top:2rem;text-align:center}
.lang-toggle a{font-size:.82rem;color:var(--muted);text-decoration:none;border-bottom:1px dashed var(--muted)}
.lang-toggle a:hover{color:var(--brand);border-color:var(--brand)}

@media(max-width:640px){.brand-panel{display:none}.form-panel{width:100%;padding:2rem 1.25rem}}
</style>
</head>
<body>
<div class="login-split">

  <!-- Brand Side -->
  <div class="brand-panel">
    <div class="deco-grid"></div>
    <div class="brand-panel-inner">
      <div class="big-logo">
        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
          <!-- Outer C arc -->
          <path d="M56,8 A44,44 0 1,0 56,92" fill="none" stroke="#C4922A" stroke-width="11" stroke-linecap="round"/>
          <!-- B vertical bar -->
          <line x1="52" y1="24" x2="52" y2="76" stroke="#C4922A" stroke-width="9" stroke-linecap="round"/>
          <!-- B top bump -->
          <path d="M52,24 Q78,24 78,40 Q78,50 52,50" fill="none" stroke="#C4922A" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
          <!-- B bottom bump -->
          <path d="M52,50 Q81,50 81,66 Q81,76 52,76" fill="none" stroke="#C4922A" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="brand-name-ar">بهنگین کریستال</div>
      <div class="brand-name-en">BANGEEN CRYSTAL</div>
      <div class="brand-tagline">
        <?= LANG==='ar' ? 'نظام نقطة البيع المتكامل' : 'Integrated Point of Sale System' ?>
      </div>
    </div>
  </div>

  <!-- Form Side -->
  <div class="form-panel">
    <div class="form-box">
      <div class="form-heading"><?= t('login') ?></div>
      <div class="form-sub"><?= LANG==='ar' ? 'أدخل بيانات حسابك للمتابعة' : 'Enter your account credentials to continue' ?></div>

      <?php if ($error): ?>
      <div class="error-msg">⚠️ <?= sanitize($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label><?= t('username') ?></label>
          <input type="text" name="username" autocomplete="username" required placeholder="<?= LANG==='ar'?'اسم المستخدم':'username' ?>">
        </div>
        <div class="form-group">
          <label><?= t('password') ?></label>
          <input type="password" name="password" autocomplete="current-password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn-login"><?= t('sign_in') ?> →</button>
      </form>

      <div class="hint-box">
        <?= LANG==='ar' ? 'تجريبي:' : 'Default:' ?>
        <code>admin</code> / <code>password</code>
      </div>

      <div class="lang-toggle">
        <a href="<?= lang_switcher_url(LANG==='ar'?'en':'ar') ?>">
          <?= LANG==='ar' ? '🇬🇧 Switch to English' : '🇮🇶 التبديل للعربية' ?>
        </a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
