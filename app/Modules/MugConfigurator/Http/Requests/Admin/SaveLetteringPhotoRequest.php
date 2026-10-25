<?php

namespace App\Modules\MugConfigurator\Http\Requests\Admin;

/**
 * A close-up of a real, fired text for „Napis z bliska”. The limits and messages match the photo under the text.
 */
class SaveLetteringPhotoRequest extends SaveMugPhotoRequest
{
    /** @var string */
    protected $errorBag = 'napis-z-bliska';

    protected function getRedirectUrl(): string
    {
        return route('admin.mug.edit').'#z-bliska';
    }
}
