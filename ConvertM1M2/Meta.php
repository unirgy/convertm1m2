<?php

trait ConvertM1M2_Meta
{
    #use ConvertM1M2_Common;

    protected function _convertGenerateMetaFiles()
    {
        $this->_convertGenerateComposerFile();
        $this->_convertGenerateRegistrationFile();
    }

    protected function _convertGenerateComposerFile()
    {
        $xml1    = $this->_readFile("@EXT/etc/config.xml", true);
        $extName = $this->_env['ext_name'];
        $version = !empty($xml1->modules->{$extName}->version) ? (string)$xml1->modules->{$extName}->version : '0.0.1';

        $data = [
            'name' => str_replace('_', '/', $extName),
            'description' => '',
            'require' => [
                'php' => '~5.5.0|~5.6.0',
            ],
            'type' => 'magento2-module',
            'version' => $version,
            'license' => [
                'Proprietary'
            ],
            'autoload' => [
                'files' => [
                    'registration.php',
                ],
                'psr-4' => [
                    str_replace('_', '\\', $extName) . '\\' => '',
                ],
            ],
            'extra' => [
                'map' => [
                    [
                        '*',
                        str_replace('_', '/', $extName)
                    ]
                ]
            ]
        ];
        $this->_writeFile('composer.json', json_encode($data, JSON_PRETTY_PRINT), true);
    }

    protected function _convertGenerateRegistrationFile()
    {
        $regCode = <<<EOT
<?php

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    '{$this->_env['ext_name']}',
    __DIR__
);

EOT;

        $this->_writeFile('registration.php', $regCode, true);
    }
}