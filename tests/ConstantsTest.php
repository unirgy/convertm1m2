<?php
require_once 'ConvertM1M2TestCase.php';
class ConstantsTest extends ConvertM1M2TestCase
{
    public function testConstantsInControllerClass()
    {
        $object = new TestableConvertM1M2();
        $code = '<?php
class Package_Namespace_IndexController extends Mage_Contacts_IndexController
{
    const XML_PATH_EMAIL_RECIPIENT  = \'contacts/email/recipient_email\';
}';
        $contents = $object->convertCodeContents($code);
        $this->assertContains('XML_PATH_EMAIL_RECIPIENT', $contents);
        echo $contents;            
        // $this->assertEquals(-1, -1);
    }
}