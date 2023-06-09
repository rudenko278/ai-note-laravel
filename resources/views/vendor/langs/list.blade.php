@extends(config('amamarul-location.layout'))
@section(config('amamarul-location.content_section'))
        @include('langs::includes.tools')
        <div class="col-md-12">

            <h2 class="text-center">{{__('Editing')}} {{__('Language')}} <code class="rounded-md px-2">{{ucfirst($lang)}} {{country2flag($lang)}}</code></h2>
            <div class="text-center flex justify-center items-center space-x-3 mt-3 mb-6">
                <div>
                    En {{country2flag('us')}}
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M18 15l3 -3l-3 -3"></path><path d="M3 12h18"></path><path d="M3 9v6"></path></svg> 
                    {{ucfirst($lang)}} {{country2flag($lang)}}
                </div>
                <a href="{{route('amamarul.translations.lang.generateJson',$lang)}}" class="btn btn-success btn-block pull-right">Generate Json File</a>
            </div>
            <table class="table table-striped">
            @foreach($list as $key => $value)
                <tr>
                    <td width="10px"><input type="checkbox" name="ids_to_edit[]" value="{{$value->id}}" /></td>
                    @foreach ($value->toArray() as $key => $element)
                        @if ($key !== 'code')
                            <td class="min-w-[400px]"><a href="#" class="testEdit" data-type="textarea" data-column="code" data-url="{{url('translations/lang/update/'.$value->code)}}" data-pk="{{$value->code}}" data-title="change" data-name="{{$key}}">{{$element}}</a></td>
                        @endif
                    @endforeach
                    <td><a href="{{route('amamarul.translations.lang.string',$value->code)}}" class="btn btn-xs btn-success">Show</a></td>
                </tr>
            @endforeach
            </table>
        </div>
@endsection
@section(config('amamarul-location.scripts_section'))
    <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.2/js/bootstrap.min.js"></script>
    <link href="//cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.0/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
    <script src="//cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.0/bootstrap3-editable/js/bootstrap-editable.min.js"></script>
<script>
$.fn.editable.defaults.mode = 'inline';
$.fn.editableform.buttons = '<button type="submit" class="btn btn-primary btn-sm editable-submit"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M14 3v4a1 1 0 0 0 1 1h4"></path><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"></path><path d="M9 15l2 2l4 -4"></path></svg></button><button type="button" class="btn btn-default btn-sm editable-cancel"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M14 3v4a1 1 0 0 0 1 1h4"></path><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"></path><path d="M10 12l4 4m0 -4l-4 4"></path></svg></button>';
$(document).ready(function() {
    $('.testEdit').editable({
        rows: 3,
        params: function(params) {
            // add additional params from data-attributes of trigger element
            params.name = $(this).editable().data('name');
            return params;
        },
        error: function(response, newValue) {
            if(response.status === 500) {
                return 'Server error. Check entered data.';
            } else {
                return response.responseText;
                // return "Error.";
            }
        }
    });
});
</script>
@endsection
