@can('edit_'.$entity)
    <a href="{{ route($entity.'.edit', [Illuminate\Support\Str::singular($entity) => $id])  }}" class="btn btn-sm btn-light">
        ✏️ Edit</a>
@endcan

@can('delete_'.$entity)
    <form method="POST" action="{{ route($entity.'.destroy', ['user' => $id]) }}" style="display: inline" onsubmit="return confirm('Are you sure you want to delete it?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-delete btn btn-sm btn-light">
            ❌
        </button>
    </form>
@endcan
