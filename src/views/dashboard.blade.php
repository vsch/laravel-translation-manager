<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title">@lang('laravel-translation-manager::messages.stats')</h3>
    </div>
    <div class="panel-body">
        <table class="table table-condensed translation-stats">
            <thead>
                <tr>
                    <th class="deleted">@lang('laravel-translation-manager::messages.deleted')</th>
                    <th class="missing">@lang('laravel-translation-manager::messages.missing')</th>
                    <th class="changed">@lang('laravel-translation-manager::messages.changed')</th>
                    <th class="group" width="20%">@lang('laravel-translation-manager::messages.group')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stats as $stat)
                    <tr>
                        <td class="deleted">{{$stat->deleted ?: '&nbsp;'}}</td>
                        <td class="missing">{{$stat->missing ?: '&nbsp;'}}</td>
                        <td class="changed">{{$stat->changed ?: '&nbsp;'}}</td>
                        @if ($stat->deleted)
                            <td class="group deleted">
                                <a href="{{action('Vsch\TranslationManager\Controller@getIndex', $stat->group)}}">{{$stat->group ?: '&nbsp;'}}</a>
                            </td>
                        @elseif ($stat->missing)
                            <td class="group missing">
                                <a href="{{action('Vsch\TranslationManager\Controller@getIndex', $stat->group)}}">{{$stat->group ?: '&nbsp;'}}</a>
                            </td>
                        @elseif ($stat->changed)
                            <td class="group changed">
                                <a href="{{action('Vsch\TranslationManager\Controller@getIndex', $stat->group)}}">{{$stat->group ?: '&nbsp;'}}</a>
                            </td>
                        @else
                            <td class="group">
                                <a href="{{action('Vsch\TranslationManager\Controller@getIndex', $stat->group)}}">{{$stat->group ?: '&nbsp;'}}</a>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
