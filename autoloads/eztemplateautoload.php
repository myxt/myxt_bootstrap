<?php

$eZTemplateOperatorArray = array();

$eZTemplateOperatorArray[] = array( 'script' => 'extension/myxt_global/autoloads/myxtlesscssoperator.php',
                                    'class' => 'MyxtLessCssOperator',
                                    'operator_names' => array( 'ezless_load', 'ezless_require' ) );

?>