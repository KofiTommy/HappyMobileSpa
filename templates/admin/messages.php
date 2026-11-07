<?php if ($error): ?><div class="admin-error" role="alert"><?= escape($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="admin-success" role="status">Message status updated.</div><?php endif; ?>
<header class="page-lead">
    <div><p class="admin-eyebrow">RECEPTION WORKSPACE</p><h2>Contact inbox</h2><p>Messages sent from the contact page appear here. Use “Reply by email” to open your email app, then mark the message replied after sending your response.</p></div>
</header>
<section class="admin-section">
<form method="get" class="filter-form"><label>Show<select name="status"><option value="">All messages</option><?php foreach (['new','read','replied','archived'] as $status): ?><option value="<?= $status ?>" <?= $inbox['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></label><button class="admin-button">Filter</button></form>
<p><?= $inbox['total'] ?> messages</p></section>
<div class="record-list">
<?php if (!$inbox['messages']): ?><div class="admin-empty"><h3>No messages in this view</h3><p>Change the filters or check again after new activity.</p></div><?php endif; ?>
<?php foreach ($inbox['messages'] as $message): ?><article class="admin-section customer-message record-card">
<div class="panel-heading"><div><span class="status"><?= escape(ucfirst($message['status'])) ?></span><h2><?= escape($message['subject']) ?></h2></div></div>
<p><strong><?= escape($message['name']) ?></strong> · <a href="mailto:<?= escape($message['email']) ?>"><?= escape($message['email']) ?></a><?php if ($message['phone']): ?> · <?= escape($message['phone']) ?><?php endif; ?><br><small><?= escape((new DateTimeImmutable($message['created_at'], new DateTimeZone('UTC')))->setTimezone(business_timezone())->format('j M Y, g:i A')) ?></small></p>
<div class="customer-message-body"><?= nl2br(escape($message['message'])) ?></div>
<div class="inline-actions"><a class="small-button" href="mailto:<?= escape($message['email']) ?>?subject=<?= escape(rawurlencode('Re: ' . $message['subject'])) ?>">Reply by email</a></div>
<form method="post" class="message-status-form form-section"><input type="hidden" name="csrf" value="<?= escape(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= $message['id'] ?>"><label>Message status<select name="status"><?php foreach (['new','read','replied','archived'] as $status): ?><option value="<?= $status ?>" <?= $message['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></label><button class="admin-button">Save status</button></form>
</article><?php endforeach; ?></div>
<nav aria-label="Message pages" class="inline-actions"><p>Page <?= $inbox['page'] ?> of <?= $inbox['pages'] ?></p><?php if ($inbox['page'] > 1): ?><a class="small-button" href="?<?= escape(http_build_query(['status'=>$inbox['status'],'page'=>$inbox['page']-1])) ?>">Previous</a><?php endif; ?> <?php if ($inbox['page'] < $inbox['pages']): ?><a class="small-button" href="?<?= escape(http_build_query(['status'=>$inbox['status'],'page'=>$inbox['page']+1])) ?>">Next</a><?php endif; ?></nav>
