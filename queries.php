<?php
	// manage.php
	// Contains the main page to manage new users
	
	require_once("config.php");
	require_once("utils.php");
	
	include('head.php');
?>
<div class="main">

	<div class="content-center">

<?php
// show all of the interesting queries
$queries = gatherQueries();
foreach ($queries as $query)
{
	displayQuery($query);
}
	
?>	
	</div> <!-- content-center -->
</div> <!-- main -->
<?php
	include ('foot.php');


// fucntion gatherQueries(): put together a list of queries to run on the server.  
// @return queries an array containing an array of a description and a sql statement
function gatherQueries()
{
	
	$queries = array();
	$queries[] = array("Show number of arrests by zipcode.","SELECT zip, COUNT(*) AS 'Total Arrests' FROM defendant GROUP BY zip ORDER BY 'Total Arrests' DESC");
	return $queries;
}

function displayQuery($query)
{
	$result = mysql_query($query[1], $GLOBALS['db']);
	print "<p><b>" . $query[0] . "</b><br/>" . $query[1] . "</p>";
	
	if (!$result) 
	{
		die('Could not run your query:' . mysql_error());
	}
	
	print "<table border='1'><tr>";
	$numFields = mysql_num_fields($result);
	for ($i = 0; $i < $numFields; $i += 1) {
        $field = mysql_fetch_field($result, $i);
        echo '<th>' . $field->name . '</th>';
    }
	print "</tr>";
	while ($row = mysql_fetch_array($result))
	{
		print "<tr>";
		for ($i=0; $i < $numFields; $i++)
			print "<td>$row[$i]</td>";
		print "</tr>";
	}
	print "</table>";
}
