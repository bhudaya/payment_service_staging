<?php

namespace Iapps\PaymentService\Common;

use Iapps\Common\Helper\FileUploader\LocalS3FileUploader;
use Iapps\Common\Helper\S3Helper\AwsS3HelperFactory;
use Aws\Common\Enum\Region;

class ReconFileS3Uploader extends LocalS3FileUploader{

    function __construct($path, $fileName)
    {
        parent::__construct();

        if( ENVIRONMENT == 'testing' )
        {
            $aws = AwsS3HelperFactory::build(Region::SINGAPORE, NULL,
                'AKIAJFZS4GMLHQNKU6PA',
                'lR4szDL5ax6oDn7JU0ijtpcJcpSa675ONfCFJdob');
            parent::__construct($aws);
        }
        else
            parent::__construct();

        $this->getS3()->setValidPeriod('3 days');
        $this->setUploadPath($path);
        $this->setFileName($fileName);
        $this->setS3Folder('interface/cms/out/');
    }
}