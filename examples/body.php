<?php ob_start(); ?>
<p>Dear ___NAME___,</p>
<p>Test email body for ___NAME___</p>
<p>Best Regards,</p>
<?php
$body = ob_get_contents();
ob_end_clean();
