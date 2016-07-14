<?php
declare(strict_types=1);
namespace Airship\Cabin\Hull\Landing;

use Airship\Cabin\Hull\Blueprint as BP;
use Airship\Alerts\{
    FileSystem\FileNotFound,
    Router\EmulatePageNotFound
};
use Airship\Engine\Security\Util;

require_once __DIR__.'/init_gear.php';

/**
 * Class PublicFiles
 * @package Airship\Cabin\Hull\Landing
 */
class PublicFiles extends LandingGear
{
    /**
     * @var string
     */
    protected $cabin = 'Hull';

    /**
     * @var BP\PublicFiles
     */
    protected $files;

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        parent::airshipLand();
        $this->files = $this->blueprint('PublicFiles');
    }

    /**
     * Download a file (assuming we are allowed to)
     *
     * @param string $path
     * @route files/(.*)
     * @throws EmulatePageNotFound
     */
    public function download(string $path, string $default = 'text/plain')
    {
        if (!$this->can('read')) {
            throw new EmulatePageNotFound;
        }
        $pieces = \Airship\chunk($path);
        $filename = \array_pop($pieces);
        try {
            $fileData = $this->files->getFileInfo(
                $this->cabin,
                $pieces,
                \urldecode($filename)
            );
            $realPath = AIRSHIP_UPLOADS  . $fileData['realname'];

            if (!\file_exists($realPath)) {
                throw new FileNotFound();
            }
            // All text/whatever needs to be text/plain; no HTML or JS payloads allowed
            $fileData['type'] = Util::downloadFileType($fileData['type'], 'text/plain');

            $c = $this->config('file.cache');
            if ($c > 0) {
                // Use caching
                \header('Cache-Control: private, max-age=' . $c);
                \header('Pragma: cache');
            }

            // Serve the file
            \header('Content-Type: ' . $fileData['type']);

            /**
             * The following headers are necessary because Microsoft Internet
             * Explorer has a documented design flaw that, left unhandled, can
             * introduce a stored XSS vulnerability. The recommended solution
             * would be "never use Internet Explorer", but some people don't have
             * a choice in that matter.
             */
            \header('Content-Disposition: attachment; filename="' . \urlencode($fileData['filename']) . '"');
            \header('X-Content-Type-Options: nosniff');
            \header('X-Download-Options: noopen');

            $this->airship_lens_object->sendStandardHeaders($fileData['type']);
            \readfile($realPath);
            exit;
        } catch (FileNotFound $ex) {
            // When all else fails, 404 not found
            \header('HTTP/1.1 404 Not Found');
            $this->lens('404');
            exit(1);
        }
    }
}
