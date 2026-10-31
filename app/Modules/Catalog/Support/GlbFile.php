<?php

namespace App\Modules\Catalog\Support;

/**
 * A binary glTF 2.0 file starts with the magic „glTF”, the version 2 and the length of the whole file,
 * all little-endian. Checking them keeps a renamed photo or a broken export out of the shop.
 */
class GlbFile
{
    public const MIME_TYPE = 'model/gltf-binary';

    public static function isGlb(string|false $path): bool
    {
        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 12);
        fclose($handle);

        if ($header === false || strlen($header) !== 12) {
            return false;
        }

        ['magic' => $magic, 'version' => $version, 'length' => $length] = unpack('a4magic/Vversion/Vlength', $header);

        return $magic === 'glTF' && $version === 2 && $length === filesize($path);
    }
}
