
@php
    use function Zhiyi\Component\ZhiyiPlus\PlusComponentPc\formatList;
    use function Zhiyi\Component\ZhiyiPlus\PlusComponentPc\getUserInfo;
@endphp
@if (!$datas->isEmpty())
@foreach ($datas as $k => $answer)
    <div class="qa-item @if ($k == 0) mt30 @endif">
        <div class="qa-body mb20 clearfix">
            @if($answer['invited'] == 0 || $answer['question']['look'] == 0 || (isset($TS) && $answer['invited'] == 1 && ((!isset($answer['could']) || $answer['could'] !== false) || $answer['question']['user_id'] == $TS['id'] || $answer['user_id'] == $TS['id'])))
                <span class="answer-body">{!! str_limit(formatList($answer['body']), 250, '...') !!}</span>
                <a class="button button-plain button-more" href="{{ route('pc:answeread', ['question' => $answer['question_id'], 'answer' => $answer['id']]) }}">查看详情</a>
            @else
                <span class="answer-body fuzzy" onclick="QA.look({{ $answer['id'] }}, '{{ $config['bootstrappers']['question:onlookers_amount'] }}' , {{ $answer['question_id'] }}, this)">
                    @php for ($i = 0; $i < 250; $i ++) {echo 'T';} @endphp
                </span>
            @endif
        </div>
        <div class="qa-toolbar feed_datas font14">
            <a
                class="liked"
                href="javascript:;"
                id="J-likes{{$answer['id']}}"
                rel="{{ $answer['likes_count'] }}"
                status="{{ (int) $answer['liked'] }}"
                onclick="liked.init({{ $answer['id'] }}, 'question', 1);"
            >
                <svg class="icon" aria-hidden="true">
                    @if($answer['liked'])
                        <use xlink:href="#icon-likered"></use>
                    @else
                        <use xlink:href="#icon-like"></use>
                    @endif
                </svg><font>{{ $answer['likes_count'] }}</font> 点赞
            </a>
            <a class="gcolor comment J-comment-show" href="javascript:;">
                <svg class="icon" aria-hidden="true"><use xlink:href="#icon-comment"></use></svg>
                <font class="cs{{$answer['id']}}">{{$answer['comments_count']}}</font> 评论
            </a>
        </div>
        @include('pcview::widgets.comments', ['id' => $answer['id'], 'comments_count' => $answer['comments_count'], 'comments_type' => 'answer', 'url' => Route('pc:answeread', ['question' => $answer['question_id'], 'answer' => $answer['id']]), 'position' => 1, 'comments_data' => $answer['comments']])
    </div>
@endforeach
<script>
    var QA = {
        look: function (answer_id, money, question_id, obj) {
            checkLogin();
            obj = obj ? obj : false;
            ly.confirm(formatConfirm('围观支付', '本次围观您需要支付' + money + '积分，是否继续围观？'), '' , '', function(){
                var _this = this;
                if (_this.lockStatus == 1) {
                    return;
                }
                _this.lockStatus = 1;
                var url ='/api/v2/question-answers/' + answer_id + '/currency-onlookers';
                axios.post(url)
                    .then(function (response) {
                        if (!obj) {
                            noticebox('围观成功', 1, '/questions/' + question_id);
                        } else {
                            noticebox('围观成功', 1);
                            var txt = response.data.answer.body.replace(/\@*\!\[\w*\]\(([https]+\:\/\/[\w\/\.]+|[0-9]+)\)/g, "[图片]");
                            var body = txt.length > 130 ? txt.substr(0, 130) + '...' : txt;
                            $(obj).removeClass('fuzzy');
                            $(obj).removeAttr('onclick');
                            $(obj).text(body);
                            $(obj).after('<a href="/questions/' + question_id + '/answers/' + answer_id + '" class="button button-plain button-more">查看详情</a>');
                            layer.closeAll();
                            _this.lockStatus = 0;
                        }
                    })
                    .catch(function (error) {
                        _this.lockStatus = 0;
                        layer.closeAll();
                        showError(error.response.data);
                    });
            });
        }
    };
</script>
@endif