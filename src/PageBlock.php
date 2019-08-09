<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 28.03.19
 * Time: 19:07
 */

namespace App\Modules\Page\Models;

use Illuminate\Database\Eloquent\Model;
use Pvtl\VoyagerPageBlocks\PageBlock;
use App\Modules\Core\Helpers\Page\Templates;
use Auth;

class Page extends Model
{
    protected $translatable = ['title', 'slug', 'body'];

    /**
     * Statuses.
     */
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_INACTIVE = 'INACTIVE';

    /**
     * List of statuses.
     *
     * @var array
     */
    public static $statuses = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $guarded = [];

    public function save(array $options = [])
    {
        // If no author has been assigned, assign the current user's id as the author of the post
        if (!$this->author_id && Auth::user()) {
            $this->author_id = Auth::user()->id;
        }

        parent::save();
    }

    public function blocks()
    {
        return $this->hasMany(PageBlock::class);
    }

    public function getLayouts()
    {
        return Templates::getTemplates('Page');;
    }
}
