<?php
/**
 * @copyright
 */

namespace App\Tests\Application\DataTransformer\Apps\DataProvider;

/**
 * @author Laura Gómez Cabero <lgomez@ext.elconfidencial.com>
 */
class EditorialForAppsDataProvider
{
    public function getPhotoOrBlogPhotoJournalist(): array
    {
        return [
            ['method' => 'blogPhoto', 'value' => 'blogPhoto.jpg'],
            ['method' => 'photo', 'value' => 'photo.jpg'],
            ['method' => 'fake', 'value' => ''],
        ];
    }
}
