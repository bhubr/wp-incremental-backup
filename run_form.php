<h2>Incremental Backup</h2>

<p>You are running: <em><?php echo $this->server_soft; ?></em></p>

<h3>Files</h3>
<ul>
<?php foreach($files as $file): ?>
	<li><?php echo $file; ?></li>
<?php endforeach; ?>
</ul>
<form action="" method="POST">

<input type="submit" class="button" value="Run" />

</form>