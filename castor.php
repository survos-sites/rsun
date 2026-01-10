<?php

use Castor\Attribute\AsTask;

use function Castor\{io,import,capture,run};
import('.castor/vendor/tacman/castor-tools/castor.php');

#[AsTask(description: 'Welcome to Castor!')]
function hello(): void
{
    $currentUser = capture('whoami');

    io()->title(sprintf('Hello %s!', $currentUser));
}
