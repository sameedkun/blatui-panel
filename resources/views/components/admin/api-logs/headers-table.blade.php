@props(['headers' => []])

@if (empty($headers))
    <p class="text-xs text-muted-foreground">—</p>
@else
    <div class="overflow-hidden rounded-md border border-border">
        <table class="w-full text-xs">
            <tbody class="divide-y divide-border">
                @foreach ($headers as $name => $value)
                    <tr wire:key="header-{{ md5($name) }}">
                        <td class="w-1/3 whitespace-nowrap bg-muted/30 px-3 py-1.5 align-top font-mono text-muted-foreground">{{ $name }}</td>
                        <td class="break-all px-3 py-1.5 font-mono">{{ is_array($value) ? implode(', ', $value) : $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
