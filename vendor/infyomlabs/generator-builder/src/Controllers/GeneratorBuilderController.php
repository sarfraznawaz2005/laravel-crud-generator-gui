<?php

namespace InfyOm\GeneratorBuilder\Controllers;

use App\Http\Controllers\Controller;
use Artisan;
use File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use InfyOm\GeneratorBuilder\Requests\BuilderGenerateRequest;
use Request;
use Response;

class GeneratorBuilderController extends Controller
{
    public function builder()
    {
        session()->put('generated_files', []);

        return view(config('infyom.generator_builder.views.builder'));
    }

    public function fieldTemplate()
    {
        return view(config('infyom.generator_builder.views.field-template'));
    }

    public function relationFieldTemplate()
    {
        return view(config('infyom.generator_builder.views.relation-field-template'));
    }

    public function generate(BuilderGenerateRequest $request)
    {
        $data = $request->all();

        // Validate fields
        if (isset($data['fields'])) {
            $this->validateFields($data['fields']);

            // prepare foreign key
            $isAnyForeignKey = collect($data['fields'])->filter(function ($field) {
                return $field['isForeign'] == true;
            });
            if (count($isAnyForeignKey)) {
                $data['fields'] = $this->prepareForeignKeyData($data['fields']);
            }
        }

        // prepare relationship
        if (isset($data['fields']) && !empty($data['relations'])) {
            $data = $this->prepareRelationshipData($data);
        }

        //return Response::json($data);


        if ($data['addOns']['datatables'] === true) {
            config(['laravel_generator.tables' => 'datatables']);
        }

        if ($data['addOns']['tests'] === true) {
            config(['laravel_generator.options.tests' => true]);
        }

        if ($data['options']['softDelete'] === true) {
            config(['laravel_generator.options.soft_delete' => true]);
        }
        
        $res = Artisan::call($data['commandType'], [
            'model'         => $data['modelName'],
            '--jsonFromGUI' => json_encode($data),
        ]);

        //return Response::json(Artisan::output());
        //return Response::json(config('laravel_generator.options.soft_delete'));

        if (session()->has('generated_files')) {
            $files = session('generated_files');
            $zip = new \ZipArchive();
            $zipFileName = storage_path("{$data['modelName']}.zip");
            $zip->open($zipFileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            foreach ($files as $file) {
                if (basename($file) === 'menu.blade.php') {
                    continue;
                }

                $zip->addFile($file, $file);
            }

            $zip->close();

            foreach ($files as $file) {
                if (basename($file) === 'web.php') {
                    continue;
                }

                @unlink($file);
            }

            return response()->download($zipFileName);
        }

        return false;

        //return Response::json('Files created successfully');
    }

    public function rollback()
    {
        $data = Request::all();
        $input = [
            'model' => $data['modelName'],
            'type'  => $data['commandType'],
        ];

        if (!empty($data['prefix'])) {
            $input['--prefix'] = $data['prefix'];
        }

        Artisan::call('infyom:rollback', $input);

        return Response::json(['message' => 'Files rollback successfully'], 200);
    }

    public function generateFromFile()
    {
        $data = Request::all();

        /** @var UploadedFile $file */
        $file = $data['schemaFile'];
        $filePath = $file->getRealPath();
        $extension = $file->getClientOriginalExtension(); // getting file extension
        if ($extension != 'json') {
            throw new \Exception('Schema file must be Json');
        }

        Artisan::call($data['commandType'], [
            'model'        => $data['modelName'],
            '--fieldsFile' => $filePath,
        ]);

        return Response::json(['message' => 'Files created successfully'], 200);
    }

    private function validateFields($fields)
    {
        $fieldsGroupBy = collect($fields)->groupBy(function ($item) {
            return strtolower($item['name']);
        });

        $duplicateFields = $fieldsGroupBy->filter(function (Collection $groups) {
            return $groups->count() > 1;
        });
        if (count($duplicateFields)) {
            throw new \Exception('Duplicate fields are not allowed');
        }

        return true;
    }

    private function prepareRelationshipData($inputData)
    {
        foreach ($inputData['relations'] as $inputRelation) {
            $relationType = $inputRelation['relationType'];
            $relation = $relationType;
            if (isset($inputRelation['foreignModel'])) {
                $relation .= ','.$inputRelation['foreignModel'];
            }
            if ($relationType == 'mtm') {
                if (isset($inputRelation['foreignTable'])) {
                    $relation .= ','.$inputRelation['foreignTable'];
                } else {
                    $relation .= ',';
                }
            }
            if (isset($inputRelation['foreignKey'])) {
                $relation .= ','.$inputRelation['foreignKey'];
            }
            if (isset($inputRelation['localKey'])) {
                $relation .= ','.$inputRelation['localKey'];
            }

            $inputData['fields'][] = [
                'type'     => 'relation',
                'relation' => $relation,
            ];
        }
        unset($inputData['relations']);

        return $inputData;
    }

    private function prepareForeignKeyData($fields)
    {
        $updatedFields = [];
        foreach ($fields as $field) {
            if ($field['isForeign'] == true) {
                if (empty($field['foreignTable'])) {
                    throw new \Exception('Foreign table required');
                }
                $inputs = explode(',', $field['foreignTable']);
                $foreignTableName = array_shift($inputs);
                // prepare dbType
                $dbType = $field['dbType'];
                $dbType .= ':unsigned:foreign';
                $dbType .= ','.$foreignTableName;
                if (!empty($inputs)) {
                    $dbType .= ','.$inputs['0'];
                } else {
                    $dbType .= ',id';
                }
                $field['dbType'] = $dbType;
            }
            $updatedFields[] = $field;
        }

        return $updatedFields;
    }

//    public function availableSchema()
//    {
//        $schemaFolder = config('infyom.laravel_generator.path.schema_files', base_path('resources/model_schemas/'));
//
//        if (!File::exists($schemaFolder)) {
//            return [];
//        }
//
//        $files = File::allFiles($schemaFolder);
//
//        $modelNames = [];
//
//        foreach ($files as $file) {
//            if(File::extension($file) == "json") {
//                $modelNames[] = File::name($file);
//            }
//        }
//
//        return Response::json($modelNames);
//    }
//
//    public function retrieveSchema($schema)
//    {
//        $schemaFolder = config('infyom.laravel_generator.path.schema_files', base_path('resources/model_schemas/'));
//
//        $filePath = $schemaFolder . $schema . ".json";
//
//        if (!File::exists($filePath)) {
//            return Response::json('not found', 402);
//        }
//
//        return Response::json(json_decode(File::get($filePath)));
//    }
}
