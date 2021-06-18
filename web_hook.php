
<?php

set_time_limit(0);

$output = shell_exec('git status');

echo "$output";