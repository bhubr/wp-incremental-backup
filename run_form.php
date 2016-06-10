<h2>Incremental Backup</h2>

<h3>Setup parameters</h3>
<table>
	<tr>
		<th>Param. name</th>
		<th>Param. value</th>
	</tr>
	<tr>
		<td>Server software</td>
		<td><?php echo $this->server_soft; ?></td>
	</tr>
	<?php foreach ($params as $key => $value): ?>
		<tr>
			<td><?php echo $key; ?></td>
			<td><?php echo $value; ?></td>
		</tr>
	<?php endforeach; ?>
</table>


<h3>Output dir content</h3>
<ul>
<?php foreach($files as $file): ?>
	<li><a href="admin-ajax.php?action=wpib_download&amp;filename=<?php echo urlencode($file); ?>"><?php echo $file; ?></a></li>
<?php endforeach; ?>
</ul>

<?php if($is_post): ?>
<h3>Process results</h3>
	<h4>New files</h4>
	<ul>
	<?php foreach($result['new'] as $file): ?>
		<li><?php echo $file; ?></li>
	<?php endforeach; ?>
	</ul>

	<h4>Modified files</h4>
	<ul>
	<?php foreach($result['modified'] as $file => $md5s): ?>
		<li><?php echo "$file => old md5: $md5s[0], new md5: $md5s[0]"; ?></li>
	<?php endforeach; ?>
	</ul>

	<h4>Deleted files</h4>
	<ul>
	<?php foreach($result['deleted'] as $file): ?>
		<li><?php echo $file; ?></li>
	<?php endforeach; ?>
	</ul>
<?php else: ?>
<h3>Run process</h3>
<form action="admin-ajax.php?action=wpib_generate" method="POST">
	<input type="submit" class="button" value="Run" />
</form>
<?php endif; ?>