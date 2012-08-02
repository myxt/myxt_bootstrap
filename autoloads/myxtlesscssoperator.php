<?php
/**
 * The MyxtLessCssOperator class implements the lesscss operator. With Less CSS
 * you can include variables inside your stylesheet, which is especially (but
 * not exclusively) handy for colorschemes in multisite installations.
 *
 */

class MyxtLessCssOperator
{

    /**
     * $Operators
     * @access private
     * @type array
     */
    private $Operators;

    /**
     * $files
     * @access static
     * @type array
     */
    static $files = array();

    /**
     * eZ Template Operator Constructor
     * @return null
     */
    function __construct()
    {
    }

    /**
     * operatorList
     * @access public
     * @return array
     */
    function operatorList()
    {
        return array( 'ezless_load', 'ezless_require' );
    }

    /**
     * namedParameterPerOperator
     * @return true
     */
    function namedParameterPerOperator()
    {
        return true;
    }


    /**
     * namedParameterList
     * @return array
     */
    function namedParameterList()
    {
        return array( 'ezless_load' => array( 'files' => array( 'type' => 'array',
                                                                'required' => false,
                                                                'default' => array() ) ),
                      'ezless_require' => array( 'files' => array( 'type' => 'array',
                                                                   'required' => true,
                                                                   'default' => array() ) ) );
    }


    /**
     * Template operator function for all functions defined on this class
     *
     * @param eZTemplate $tpl
     * @param string $operatorName
     * @param array $operatorParameters
     * @param string $rootNamespace
     * @param string $currentNamespace
     * @param null|mixed $operatorValue
     * @param array $namedParameters
     */
    function modify( eZTemplate $tpl, $operatorName, array $operatorParameters, $rootNamespace, $currentNamespace, &$operatorValue, array $namedParameters )
    {

        switch ( $operatorName )
        {
            case 'ezless_load':
                $operatorValue = $this->loadFiles( $namedParameters['files'] );
                break;
            case 'ezless_require':
                $operatorValue = $this->addFiles( $namedParameters['files'] );
                break;
        }

    }


    /**
     * loadFiles
     * @param array $files
     * @return string $html generated html tags
     */
    public function loadFiles( $files )
    {
        $pageLayoutFiles = array();
        if( is_array( $files ) )
            foreach( $files as $file )
                if( is_array( $file ) )
                    foreach( $file as $f )
                        $pageLayoutFiles[] = $f;
                else
                    $pageLayoutFiles[] = $file;
        else
            $pageLayoutFiles[] = $files;

        $files = $this->prependArray( self::$files, $pageLayoutFiles );

        return $this->generateTag( $files );
    }


    /**
     * addFiles
     * @param array|string $files
     * @return null
     */
    public function addFiles( $files )
    {
        if( is_array( $files ) )
            foreach( $files as $file )
                self::$files[] = $file;
        else
            self::$files[] = $files;

    }


    /**
     * prependArray
     * @description prepends the $prepend array in front of $array
     * @param array $array
     * @param array $prepend
     * @return array $return
     */
    private function prependArray( $array, $prepend )
    {
        $return = $prepend;

        foreach( $array as $value)
            $return[] = $value;

        return $return;
    }


    /**
     * generateTag
     * @param array $files
     * @return string $html
     */
    private function generateTag( $files )
    {
        eZDebug::writeDebug( $files, 'MyxtLessOperator::generateTag' );
        $sys = eZSys::instance();
        $ini = eZINI::instance();

        $html = $cssContent = '';
        $bases = eZTemplateDesignResource::allDesignBases();
        $triedFiles = array();

        $path = $sys->cacheDirectory() . '/public/stylesheets';
        $packerLevel = $this->getPackerLevel();

        foreach( $files as $file )
        {
            if( substr( $file, 0, 1 ) == '/' ) // /lib
            {
                $match = eZTemplateDesignResource::fileMatch( $bases, '', $file, $triedFiles );
            }
            else
                $match = eZTemplateDesignResource::fileMatch( $bases, '', 'stylesheets/'.$file, $triedFiles );
                
            if( $match )
            {

                try
                {
                    $file = $path . '/' . $file . '.css';
                    $clusterFile = eZClusterFileHandler::instance( $file );

                    if( !$clusterFile->fetchContents() || $packerLevel <= 1 )
                    {
                        eZDebug::writeDebug( 'Regenerating less.' );
                        $less = new lessc( $match['path'] );
                        $parsedContent = $less->parse();
                        $parsedContent = ezjscPacker::fixImgPaths( $parsedContent, $match['path'] );
                        if( $packerLevel > 1 )
                            $parsedContent = $this->optimizeCSS( $parsedContent, $packerLevel );
                        $clusterFile->storeContents( $parsedContent, 'ezless', 'text/css' );
                    }
                    
                    eZURI::transformURI( $file, true );
                    $html .= '<link rel="stylesheet" type="text/css" href="' . $file . '" />' . PHP_EOL;
                }
                catch( Exception $e )
                {
                    eZDebug::writeError( $e->getMessage(), 'ezLessOperator for ' . $match['path'] );
                }

            }
        }

        return $html;
    }


    /**
     * Returns packer Level as defined in ezjscore.ini
     * borrowed from ezjscore
     * @return int
     */
    private function getPackerLevel()
    {
        $ezjscINI = eZINI::instance( 'ezjscore.ini' );
        // Only pack files if Packer is enabled and if not set DevelopmentMode is disabled
        if ( $ezjscINI->hasVariable( 'eZJSCore', 'Packer' ) )
        {
            $packerIniValue = $ezjscINI->variable( 'eZJSCore', 'Packer' );
            if( $packerIniValue === 'diabled' || eZINI::instance()->variable( 'TemplateSettings', 'DevelopmentMode' ) === 'enabled' )
                return 0;
            elseif ( is_numeric( $packerIniValue ) )
                return (int) $packerIniValue;
        }
        else
        {
            if ( eZINI::instance()->variable( 'TemplateSettings', 'DevelopmentMode' ) === 'enabled' )
            {
                return 0;
            }
            else return 3;
        }
    }

    /**
     * Optimizes CSS content using ezjscore
     * Using either INI optimzers or optimizeCSS if ezjscore is an older version
     * @param string $content
     * @param int $packerLevel
     * @return string
     */
    private function optimizeCSS( $content, $packerLevel )
    {
        $ezjscINI = eZINI::instance( 'ezjscore.ini' );
        if( $ezjscINI->hasVariable( 'eZJSCore', 'CssOptimizer' ) )
        {
            foreach( $ezjscINI->variable( 'eZJSCore', 'CssOptimizer' ) as $optimizer )
            {
                $content = call_user_func( array( $optimizer, 'optimize' ), $content, $packerLevel );
            }
        }
        elseif ( method_exists( 'ezjscPacker', 'optimizeCSS') )
        {
            $content = ezjscPacker::optimizeCSS( $content, $packerLevel );
        }

        return $content;
    }
}

?>