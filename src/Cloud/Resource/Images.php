<?php

declare(strict_types=1);

namespace GoSuccess\Hetzner\Cloud\Resource;

use GoSuccess\Hetzner\Cloud\Model\Image;

/**
 * The images (`GET /images`); filters `type`, `status`, `name`, `label_selector`, `architecture`, `include_deprecated`, `sort`.
 *
 * @extends Listing<Image>
 */
final class Images extends Listing
{
    protected const string PATH = '/images';

    protected const string ITEMS = 'images';

    protected const string ITEM = 'image';

    protected function map(array $data, string $path): Image
    {
        return Image::fromJson($data, $path);
    }
}
