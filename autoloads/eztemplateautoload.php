<?php

$eZTemplateOperatorArray = array();

$eZTemplateOperatorArray[] = array( 'script' => 'extension/myxt_bootstrap/autoloads/myxtlesscssoperator.php',
                                    'class' => 'MyxtLessCssOperator',
                                    'operator_names' => array( 'ezless_load', 'ezless_require' ) );

?>