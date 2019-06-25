<?php

namespace Maatwebsite\LaravelNovaExcel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Resource;
use Maatwebsite\Excel\Importer;
use Maatwebsite\Excel\Validators\Failure;
use Laravel\Nova\Http\Requests\NovaRequest;
use Maatwebsite\LaravelNovaExcel\Actions\ImportExcel;
use Maatwebsite\LaravelNovaExcel\Models\Import;
use Maatwebsite\LaravelNovaExcel\Models\Upload;
use Maatwebsite\Excel\Validators\ValidationException;
use Illuminate\Foundation\Validation\ValidatesRequests;

class UploadsImportsController extends Controller
{
    use ValidatesRequests;

    /**
     * @var Import
     */
    protected $import;

    /**
     * @param Upload      $upload
     * @param NovaRequest $request
     * @param Importer    $importer
     *
     * @return JsonResponse
     */
    public function store(Upload $upload, NovaRequest $request, Importer $importer)
    {
        $import = Import::fromUpload(
            $upload,
            $request->input('mapping')
        );

        $import->update([
            'status' => 'running',
        ]);

        try {
            $importer->import(
                $this->action($import->getResourceInstance(), $request)->getImportObject($import, $request),
                $import->upload->path,
                $import->upload->disk
            );

            $import->update([
                'status' => Import::STATUS_COMPLETED,
            ]);
        } catch (ValidationException $e) {
            $import->update([
                'status' => Import::STATUS_FAILED,
            ]);

            return new JsonResponse([
                'message' => __('File could not be imported.'),
                'errors'  => $this->errors($e),
            ], 422);
        }

        return new JsonResponse([
            'status' => 'OK',
        ]);
    }

    /**
     * @param ValidationException $e
     *
     * @return Collection
     */
    private function errors(ValidationException $e)
    {
        return collect($e->failures())->groupBy(function (Failure $failure) {
            return $failure->row();
        })->map(function (Collection $row, $rowNumber) {
            return [
                'row'     => $rowNumber,
                'message' => $row->flatMap->errors()->implode(' '),
            ];
        })->values();
    }

    /**
     * @param Resource    $resource
     * @param NovaRequest $request
     *
     * @return ImportExcel
     */
    protected function action(Resource $resource, NovaRequest $request)
    {
        return collect($resource->actions($request))->first(function (Action $action) {
            return $action instanceof ImportExcel;
        });
    }
}