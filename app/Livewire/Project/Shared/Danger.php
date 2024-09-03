<?php

namespace App\Livewire\Project\Shared;

use App\Jobs\DeleteResourceJob;
use App\Models\Service;
use App\Models\ServiceDatabase;
use App\Models\ServiceApplication;
use Livewire\Component;
use Visus\Cuid2\Cuid2;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class Danger extends Component
{
    public $resource;
    public $resourceName;
    public $projectUuid;
    public $environmentName;
    public bool $delete_configurations = true;
    public bool $delete_volumes = true;
    public bool $docker_cleanup = true;
    public bool $delete_connected_networks = true;
    public ?string $modalId = null;
    public string $resourceDomain = '';

    public function mount()
    {
        $parameters = get_route_parameters();
        $this->modalId = new Cuid2;
        $this->projectUuid = data_get($parameters, 'project_uuid');
        $this->environmentName = data_get($parameters, 'environment_name');

        if ($this->resource === null) {
            if (isset($parameters['service_uuid'])) {
                $this->resource = Service::where('uuid', $parameters['service_uuid'])->first();
            } elseif (isset($parameters['stack_service_uuid'])) {
                $this->resource = ServiceApplication::where('uuid', $parameters['stack_service_uuid'])->first()
                    ?? ServiceDatabase::where('uuid', $parameters['stack_service_uuid'])->first();
            }
        }

        if ($this->resource === null) {
            $this->resourceName = 'Unknown Resource';
            return;
        }

        if (!method_exists($this->resource, 'type')) {
            $this->resourceName = 'Unknown Resource';
            return;
        }

        switch ($this->resource->type()) {
            case 'application':
                $this->resourceName = $this->resource->name ?? 'Application';
                break;
            case 'standalone-postgresql':
            case 'standalone-redis':
            case 'standalone-mongodb':
            case 'standalone-mysql':
            case 'standalone-mariadb':
            case 'standalone-keydb':
            case 'standalone-dragonfly':
            case 'standalone-clickhouse':
                $this->resourceName = $this->resource->name ?? 'Database';
                break;
            case 'service':
                $this->resourceName = $this->resource->name ?? 'Service';
                break;
            case 'service-application':
                $this->resourceName = $this->resource->name ?? 'Service Application';
                break;
            case 'service-database':
                $this->resourceName = $this->resource->name ?? 'Service Database';
                break;
            default:
                $this->resourceName = 'Unknown Resource';
        }
    }

    public function delete($password)
    {
        if (!Hash::check($password, Auth::user()->password)) {
            $this->addError('password', 'The provided password is incorrect.');
            return;
        }

        if (!$this->resource) {
            $this->addError('resource', 'Resource not found.');
            return;
        }

        try {
            $this->resource->delete();
            DeleteResourceJob::dispatch(
                $this->resource,
                $this->delete_configurations,
                $this->delete_volumes,
                $this->docker_cleanup,
                $this->delete_connected_networks
            );

            return redirect()->route('project.resource.index', [
                'project_uuid' => $this->projectUuid,
                'environment_name' => $this->environmentName,
            ]);
        } catch (\Throwable $e) {
            return handleError($e, $this);
        }
    }

    public function render()
    {
        return view('livewire.project.shared.danger', [
            'checkboxes' => [
                ['id' => 'delete_volumes', 'label' => 'All associated volumes with this resource will be permanently deleted'],
                ['id' => 'delete_connected_networks', 'label' => 'All connected networks with this resource will be permanently deleted (predefined networks will not be deleted)'],
                ['id' => 'delete_configurations', 'label' => 'All configuration files will be permanently deleted form the server'],
                ['id' => 'docker_cleanup', 'label' => 'Docker cleanup will be run on the server which removes builder cache and unused images'],
                // ['id' => 'delete_associated_backups_locally', 'label' => 'All backups associated with this Ressource will be permanently deleted from local storage.'],
                // ['id' => 'delete_associated_backups_s3', 'label' => 'All backups associated with this Ressource will be permanently deleted from the selected S3 Storage.'],
                // ['id' => 'delete_associated_backups_sftp', 'label' => 'All backups associated with this Ressource will be permanently deleted from the selected SFTP Storage.']
            ]
        ]);
    }
}
