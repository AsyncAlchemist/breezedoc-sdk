<?php

declare(strict_types=1);

namespace Breezedoc\Tests\Integration;

use Breezedoc\Breezedoc;
use Breezedoc\Config\Configuration;
use Breezedoc\Web\FileSessionStore;

class WebPdfIntegrationTest extends IntegrationTestCase
{
    public function testDownloadPdfLogsInAndReturnsPdf(): void
    {
        $credentials = $this->getWebCredentials();

        // Isolate the session cache to a temp file so we don't touch ~/.breezedoc.
        $sessionPath = sys_get_temp_dir() . '/breezedoc_web_it_' . uniqid() . '/session.json';

        $config = new Configuration((string) $this->apiToken);
        $config->setWebLogin($credentials['email'], $credentials['password'])
            ->setSessionStore(new FileSessionStore($sessionPath));

        $client = Breezedoc::client($config);

        $document = $this->firstCompletedDocumentId($client);
        if ($document === null) {
            $this->markTestSkipped('No completed document available to download.');
        }

        try {
            $pdf = $client->documents()->downloadPdf($document);

            $this->assertNotEmpty($pdf);
            $this->assertStringStartsWith('%PDF', $pdf, 'Downloaded content should be a PDF');

            // A cached session should now exist and be reused on a second call.
            $this->assertFileExists($sessionPath);
            $pdfAgain = $client->documents()->downloadPdf($document);
            $this->assertSame($pdf, $pdfAgain);
        } finally {
            if (is_file($sessionPath)) {
                unlink($sessionPath);
            }
            $dir = dirname($sessionPath);
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    private function firstCompletedDocumentId(\Breezedoc\Client $client): ?int
    {
        foreach ($client->documents()->list() as $document) {
            if ($document->getCompletedAt() !== null) {
                return $document->getId();
            }
        }

        return null;
    }
}
