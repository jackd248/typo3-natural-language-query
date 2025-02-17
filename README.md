<div align="center">

# TYPO3 extension `typo3_natural_language_query`

</div>

This extension provides a natural language query interface for TYPO3.

## Console command

```bash
vendor/bin/typo3 nlq:ask
```

## Service

```php
<?php

use Xima\NaturalLanguageQuery\Service\Solver;

class DemoController {

    public function __construct(Solver $solver) {}
    
    public function demoAction() {
        $this->solver->solve('How many pages are available in the page tree?');
    }
}