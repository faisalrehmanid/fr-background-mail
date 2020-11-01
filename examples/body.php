<?php ob_start(); ?>
<p>Dear User,</p>
<p>Test email body</p>
<p>Best Regards,</p>
<?php
$body = ob_get_contents();
ob_end_clean();
?>