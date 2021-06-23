
<?php

set_time_limit(0);

$output = shell_exec('./pull.sh');

echo "$output";
