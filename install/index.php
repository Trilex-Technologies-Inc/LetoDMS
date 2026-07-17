<?php
define("LETODMS_INSTALL", "on");
include("../inc/inc.Settings.php");
$settings = new Settings();
$rootDir = realpath ("..");
$settings->_rootDir = $rootDir.'/';

include("../inc/inc.Language.php");
include "../languages/English/lang.inc";
include("../inc/inc.ClassUI.php");

(new UI($GLOBALS['theme'] ?? 'bootstrap'))->htmlStartPage("INSTALL");
?>
<style type="text/css">
	* { box-sizing: border-box; }
	body {
		min-height: 100vh;
		margin: 0;
		color: #263545;
		background:
			radial-gradient(circle at 8% 10%, rgba(78, 157, 218, .18), transparent 28rem),
			linear-gradient(145deg, #eef5fa 0%, #f8fafc 52%, #edf3f7 100%);
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
		font-size: 14px;
		line-height: 1.65;
	}
	a { color: #246fa8; }
	a:hover { color: #174e78; }
	.install-welcome {
		width: calc(100% - 40px);
		max-width: 1040px;
		margin: 0 auto;
		padding: 48px 0 36px;
	}
	.install-brand {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 24px;
		color: #193c57;
		font-size: 17px;
		font-weight: 700;
		letter-spacing: -.01em;
	}
	.install-mark {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 40px;
		height: 40px;
		color: #fff;
		background: linear-gradient(145deg, #2f83bd, #205c89);
		border-radius: 11px;
		box-shadow: 0 7px 18px rgba(30, 92, 137, .22);
	}
	.install-mark svg { width: 21px; height: 21px; fill: currentColor; }
	.install-card {
		overflow: hidden;
		background: rgba(255, 255, 255, .96);
		border: 1px solid rgba(190, 207, 220, .8);
		border-radius: 18px;
		box-shadow: 0 22px 55px rgba(41, 65, 84, .13);
	}
	.install-card-header {
		position: relative;
		padding: 42px 48px 38px;
		color: #fff;
		background: linear-gradient(120deg, #173d58 0%, #246b99 100%);
	}
	.install-card-header:after {
		position: absolute;
		top: -75px;
		right: -55px;
		width: 270px;
		height: 270px;
		content: "";
		border: 45px solid rgba(255, 255, 255, .06);
		border-radius: 50%;
	}
	.install-step {
		position: relative;
		z-index: 1;
		display: inline-block;
		margin-bottom: 14px;
		padding: 4px 10px;
		color: #d8edfa;
		background: rgba(255, 255, 255, .11);
		border: 1px solid rgba(255, 255, 255, .17);
		border-radius: 99px;
		font-size: 11px;
		font-weight: 700;
		letter-spacing: .08em;
		text-transform: uppercase;
	}
	.install-card-header h1 {
		position: relative;
		z-index: 1;
		max-width: 720px;
		margin: 0 0 10px;
		color: #fff;
		font-size: 32px;
		font-weight: 700;
		line-height: 1.2;
		letter-spacing: -.025em;
	}
	.install-card-header p {
		position: relative;
		z-index: 1;
		max-width: 650px;
		margin: 0;
		color: rgba(255, 255, 255, .77);
		font-size: 15px;
	}
	.install-card-body { padding: 34px 48px 12px; }
	.install-copy {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 18px;
	}
	.install-copy p {
		position: relative;
		margin: 0;
		padding: 46px 20px 20px;
		color: #536474;
		background: #f7fafc;
		border: 1px solid #e2eaf0;
		border-radius: 12px;
		font-size: 13px;
		line-height: 1.65;
	}
	.install-copy p:before {
		position: absolute;
		top: 18px;
		left: 20px;
		width: 18px;
		height: 18px;
		color: #2d7eaf;
		content: "\2713";
		font-size: 17px;
		font-weight: 800;
		line-height: 18px;
	}
	.install-copy a { overflow-wrap: anywhere; font-weight: 600; }
	.install-actions {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 20px;
		padding: 26px 48px 34px;
	}
	.install-hint { color: #7a8996; font-size: 12px; }
	.install-button,
	.install-button:visited {
		display: inline-flex;
		align-items: center;
		gap: 12px;
		min-width: 190px;
		justify-content: center;
		padding: 12px 20px;
		color: #fff;
		background: #2879ad;
		border: 1px solid #246e9e;
		border-radius: 9px;
		box-shadow: 0 7px 16px rgba(36, 110, 158, .2);
		font-size: 14px;
		font-weight: 700;
		text-decoration: none;
		transition: background .18s ease, box-shadow .18s ease, transform .18s ease;
	}
	.install-button:hover,
	.install-button:focus {
		color: #fff;
		background: #1f6593;
		box-shadow: 0 9px 20px rgba(36, 110, 158, .28);
		text-decoration: none;
		transform: translateY(-1px);
	}
	.install-button span { font-size: 19px; font-weight: 400; line-height: 1; }
	.disclaimer, .footNote {
		width: calc(100% - 40px);
		max-width: 1040px;
		margin: 0 auto 12px;
		padding: 0;
		color: #788894;
		border: 0;
		font-size: 11px;
		text-align: center;
	}
	.footNote { padding-bottom: 24px; }
	@media (max-width: 800px) {
		.install-welcome { padding-top: 28px; }
		.install-card-header, .install-card-body { padding-right: 28px; padding-left: 28px; }
		.install-copy { grid-template-columns: 1fr; }
		.install-copy p { padding-top: 20px; padding-left: 52px; }
		.install-copy p:before { top: 23px; }
		.install-actions { padding-right: 28px; padding-left: 28px; }
	}
	@media (max-width: 520px) {
		.install-welcome { width: calc(100% - 24px); padding-top: 18px; }
		.install-brand { margin-bottom: 16px; }
		.install-card { border-radius: 13px; }
		.install-card-header { padding: 30px 22px; }
		.install-card-header h1 { font-size: 26px; }
		.install-card-body { padding: 22px 16px 8px; }
		.install-actions { align-items: stretch; flex-direction: column; padding: 18px 16px 24px; }
		.install-button { width: 100%; }
	}
</style>
<main class="install-welcome">
	<div class="install-brand">
		<span class="install-mark" aria-hidden="true">
			<svg viewBox="0 0 24 24"><path d="M6 2h8l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm7 2v5h5l-5-5zM8 13v2h8v-2H8zm0 4v2h6v-2H8z"/></svg>
		</span>
		<span>letoDMS</span>
	</div>
	<section class="install-card" aria-labelledby="install-title">
		<header class="install-card-header">
			<div class="install-step">Installation &middot; Welcome</div>
			<h1 id="install-title"><?php echo getMLText('settings_install_welcome_title'); ?></h1>
			<p>A few quick checks before configuring your document management system.</p>
		</header>
		<div class="install-card-body">
			<div class="install-copy"><?php echo getMLText('settings_install_welcome_text'); ?></div>
		</div>
		<footer class="install-actions">
			<span class="install-hint">You can review all settings before installation begins.</span>
			<a class="install-button" href="install.php">
				<?php echo getMLText("settings_start_install"); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
		</footer>
	</section>
</main>
<?php
(new UI($GLOBALS['theme'] ?? 'bootstrap'))->htmlEndPage();
?>
