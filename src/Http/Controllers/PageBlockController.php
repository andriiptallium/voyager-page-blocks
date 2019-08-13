<?php

namespace Pvtl\VoyagerPageBlocks\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Pvtl\VoyagerPageBlocks\Page;
use Pvtl\VoyagerPageBlocks\PageBlock;
use Pvtl\VoyagerPageBlocks\Traits\Blocks;
use Pvtl\VoyagerPageBlocks\Validators\BlockValidators;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\VoyagerBaseController;
use Storage;

class PageBlockController extends VoyagerBaseController
{
    use Blocks;

    public function index(Request $request)
    {
        return redirect('/admin/pages');
    }

    /**
     * POST B(R)EAD - Read data.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return View
     */
    public function edit(Request $request, $id)
    {
        $page = Page::findOrFail($id);

        $pageBlocks = $page->blocks->sortBy('order');


        foreach ($pageBlocks as $block) {
            foreach ($block->data as $field => $value) {
                $block->$field = $value;
            }
        }

        return view('voyager::page-blocks.edit-add', [
            'page' => $page,
            'pageBlocks' => $page->blocks->sortBy('order'),
        ]);
    }

    /**
     * POST BR(E)AD - Edit data.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $block = PageBlock::findOrFail($id);
        $template = $block->template();
        $dataType = Voyager::model('DataType')->where('slug', '=', 'page-blocks')->first();

        $translatable = [];
        $fields = get_object_vars($template->fields);

        foreach ($fields as $key => $item) {
            if ($item->translatable) {
                $translatable[] = $key;
            }
        }

        $block->setTranslatableFields($translatable);

        // Prepare Translations and Transform data
        $translations = is_bread_translatable($block)
            ? $block->prepareTranslations($request)
            : [];

        // Get all block data & validate
        $data = [];

        foreach ($template->fields as $row) {
            $existingData = $block->data;
            unset($block->{$row->field});
            if (
                $row->partial === 'voyager::formfields.image'
                || $row->partial === 'voyager::formfields.multiple_images'
            ) {
                if (is_null($request->file($row->field))) {
                    if (isset($existingData->{$row->field})) {
                        $data[$row->field] = $existingData->{$row->field};
                    }

                    continue;
                }

                $data[$row->field] = $request->file($row->field);
            } else {
                $data[$row->field] = $request->input($row->field);
            }
        }

        // Just.Do.It! (Nike, TM)
        $validator = BlockValidators::validateBlock($request, $block);
        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput()
                ->with([
                    'message' => __('voyager::json.validation_errors'),
                    'alert-type' => 'error',
                ]);
        }

        $data = $this->uploadImages($request, $data, $existingData);

        $block->data = $data;
        $block->path = $block->type === 'include' ? $request->input('path') : $block->path;
        $block->is_hidden = $request->has('is_hidden');
        $block->is_delete_denied = $request->has('is_delete_denied');
        $block->cache_ttl = $request->input('cache_ttl');
        $block->save();

        // Save translations if applied
        $block->saveTranslations($translations);

        return redirect()
            ->to(URL::previous() . "#block-id-" . $id)
            ->with([
                'message' => __('voyager::generic.successfully_updated') . " {$dataType->display_name_singular}",
                'alert-type' => 'success',
            ]);
    }

    /**
     * POST - Order data.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function sort(Request $request)
    {
        $blockOrder = json_decode($request->input('order'));

        foreach ($blockOrder as $index => $item) {
            $block = PageBlock::findOrFail($item->id);
            $block->order = $index + 1;
            $block->save();
        }
    }

    /**
     * POST - Minimize Block
     *
     * @param \Illuminate\Http\Request $request
     */
    public function minimize(Request $request)
    {
        $block = PageBlock::findOrFail((int)$request->id);
        $block->is_minimized = (int)$request->is_minimized;
        $block->save();
    }

    /**
     * POST - Change Page Layout
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id - the page id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function changeLayout(Request $request, $id)
    {
        $page = Page::findOrFail((int)$id);
        $page->layout = $request->layout;
        $page->save();

        return redirect()
            ->back()
            ->with([
                'message' => __('voyager::generic.successfully_updated') . " Page Layout",
                'alert-type' => 'success',
            ]);
    }

    /**
     * POST BRE(A)D - Store data.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $page = Page::findOrFail($request->input('page_id'));
        $dataType = Voyager::model('DataType')->where('slug', '=', 'page-blocks')->first();

        if ($request->input('type') === 'include') {
            $type = $request->input('type');
            $path = '\Pvtl\VoyagerFrontend\Http\Controllers\PostController::recentBlogPosts()';
        } else {
            list($type, $path) = explode('|', $request->input('type'));
        }

        $block = $page->blocks()->create([
            'type' => $type,
            'path' => $path,
            'data' => $type === 'include' ? '' : $this->generatePlaceholders($request),
            'order' => time(),
        ]);

        return redirect()
            ->route('voyager.page-blocks.edit', array($page->id, '#block-id-' . $block->id))
            ->with([
                'message' => __('voyager::generic.successfully_added_new') . " {$dataType->display_name_singular}",
                'alert-type' => 'success',
            ]);
    }

    /**
     * DELETE BREA(D) - Delete data.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, $id)
    {
        $block = PageBlock::findOrFail($id);
        $dataType = Voyager::model('DataType')->where('slug', '=', 'page-blocks')->first();

        try {
            $block->delete();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with([
                    'message' => "Unable to delete {$dataType->display_name_singular}",
                    'alert-type' => 'error',
                ]);
        }

        return redirect()
            ->back()
            ->with([
                'message' => __('voyager::generic.successfully_deleted') . " {$dataType->display_name_singular}",
                'alert-type' => 'success',
            ]);
    }

    public function deleteMultipleImage(Request $request)
    {
        $block = PageBlock::findOrFail($request['block_id']);
        $data = $block->data;
        $images = json_decode($block->data->{$request['field']}, true);
        $key = array_search($request['file_name'], $images);
        $file_to_delete = $images[$key];
        unset($images[$key]);
        $data->images = json_encode($images);
        $block->data = $data;
        if (Storage::disk(config('voyager.storage.disk'))->exists($file_to_delete)) {
            Storage::disk(config('voyager.storage.disk'))->delete($file_to_delete);
        }
        $block->save();
    }
}
