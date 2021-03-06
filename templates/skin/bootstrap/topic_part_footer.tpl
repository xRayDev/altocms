	{assign var="oBlog" value=$oTopic->getBlog()}
	{assign var="oUser" value=$oTopic->getUser()}
	{assign var="oVote" value=$oTopic->getVote()}
	{assign var="oFavourite" value=$oTopic->getFavourite()}

    <div class="topic-share" id="topic_share_{$oTopic->getId()}">
    {hookb run="topic_share" topic=$oTopic bTopicList=$bTopicList}
        <div class="yashare-auto-init" data-yashareTitle="{$oTopic->getTitle()|escape:'html'}" data-yashareLink="{$oTopic->getUrl()}" data-yashareL10n="ru" data-yashareType="button" data-yashareQuickServices="yaru,vkontakte,facebook,twitter,odnoklassniki,moimir,lj,gplus"></div>
    {/hookb}
    </div>

    {if !$bTopicList}
    <ul class="topic-tags js-favourite-insert-after-form js-favourite-tags-topic-{$oTopic->getId()}">
        <li><i class="icon-tags"></i></li>

        {strip}
            {if $oTopic->getTagsArray()}
                {foreach from=$oTopic->getTagsArray() item=sTag name=tags_list}
                    <li>{if !$smarty.foreach.tags_list.first}, {/if}<a rel="tag" href="{router page='tag'}{$sTag|escape:'url'}/">{$sTag|escape:'html'}</a></li>
                {/foreach}
                {else}
                <li>{$aLang.topic_tags_empty}</li>
            {/if}

            {if $oUserCurrent}
                {if $oFavourite}
                    {foreach from=$oFavourite->getTagsArray() item=sTag name=tags_list_user}
                        <li class="topic-tags-user js-favourite-tag-user">, <a rel="tag" href="{$oUserCurrent->getUserWebPath()}favourites/topics/tag/{$sTag|escape:'url'}/">{$sTag|escape:'html'}</a></li>
                    {/foreach}
                {/if}

                <li class="topic-tags-edit js-favourite-tag-edit" {if !$oFavourite}style="display:none;"{/if}>
                    <a href="#" onclick="return ls.favourite.showEditTags({$oTopic->getId()},'topic',this);" class="link-dotted">{$aLang.favourite_form_tags_button_show}</a>
                </li>
            {/if}
        {/strip}
    </ul>
    {/if}

	<footer class="topic-footer well well-small">

		<ul class="topic-info">
			<li id="vote_area_topic_{$oTopic->getId()}" class="vote
																{if $oVote || ($oUserCurrent && $oTopic->getUserId() == $oUserCurrent->getId()) || strtotime($oTopic->getDateAdd()) < $smarty.now-$oConfig->GetValue('acl.vote.topic.limit_time')}
																	{if $oTopic->getRating() > 0}
																		vote-count-positive
																	{elseif $oTopic->getRating() < 0}
																		vote-count-negative
																	{/if}
																{/if}
																
																{if $oVote} 
																	voted
																	
																	{if $oVote->getDirection() > 0}
																		voted-up
																	{elseif $oVote->getDirection() < 0}
																		voted-down
																	{/if}
																{/if}">
				{if $oVote || ($oUserCurrent && $oTopic->getUserId() == $oUserCurrent->getId()) || strtotime($oTopic->getDateAdd()) < $smarty.now-$oConfig->GetValue('acl.vote.topic.limit_time')}
					{assign var="bVoteInfoShow" value=true}
				{/if}
                <div class="btn-group">
                    {if {cfg name='view.vote_topic.type'} == 'plus_minus'}
                        <div class="vote-up btn btn-small" onclick="return ls.vote.vote({$oTopic->getId()},this,1,'topic');"><i class="icon-plus"></i></div>
                        <div class="vote-count btn btn-small {if $bVoteInfoShow}js-infobox-vote-topic{/if}" id="vote_total_topic_{$oTopic->getId()}" title="{$aLang.topic_vote_count}: {$oTopic->getCountVote()}">
                            {if $bVoteInfoShow}
                                {if $oTopic->getRating() > 0}+{/if}{$oTopic->getRating()}
                            {else}
                                <a href="#" onclick="return ls.vote.vote({$oTopic->getId()},this,0,'topic');">?</a>
                            {/if}
                        </div>
                        <div class="vote-down btn btn-small" onclick="return ls.vote.vote({$oTopic->getId()},this,-1,'topic');"><i class="icon-minus"></i></div>
                    {elseif {cfg name='view.vote_topic.type'} == 'minus_plus'}
                        <div class="vote-down btn btn-small" onclick="return ls.vote.vote({$oTopic->getId()},this,-1,'topic');"><i class="icon-minus"></i></div>
                        <div class="vote-count btn btn-small {if $bVoteInfoShow}js-infobox-vote-topic{/if}" id="vote_total_topic_{$oTopic->getId()}" title="{$aLang.topic_vote_count}: {$oTopic->getCountVote()}">
                            {if $bVoteInfoShow}
                                {if $oTopic->getRating() > 0}+{/if}{$oTopic->getRating()}
                                {else}
                                <a href="#" onclick="return ls.vote.vote({$oTopic->getId()},this,0,'topic');">?</a>
                            {/if}
                        </div>
                        <div class="vote-up btn btn-small" onclick="return ls.vote.vote({$oTopic->getId()},this,1,'topic');"><i class="icon-plus"></i></div>
                    {/if}
                </div>
				{if $bVoteInfoShow}
					<div id="vote-info-topic-{$oTopic->getId()}" class="stat-topic" style="display: none;">
                        <i class="icon-thumbs-up icon-white"></i>&nbsp; {$oTopic->getCountVoteUp()}<br/>
                        <i class="icon-eye-open icon-white"></i>&nbsp; {$oTopic->getCountVoteAbstain()}<br/>
                        <i class="icon-thumbs-down icon-white"></i>&nbsp; {$oTopic->getCountVoteDown()}<br/>
						{hook run='topic_show_vote_stats' topic=$oTopic}
					</div>
				{/if}
			</li>

			<li class="topic-info-author"><a rel="author" href="{$oUser->getUserWebPath()}"><i class="icon-user"></i>{$oUser->getLogin()}</a></li>
            <li class="topic-info-share"><a href="#" class="icon-share" title="{$aLang.topic_share}" onclick="jQuery('#topic_share_{$oTopic->getId()}').slideToggle(); return false;"></a></li>
            <li class="topic-info-favourite">
				<div onclick="return ls.favourite.toggle({$oTopic->getId()},this,'topic');" class="favourite {if $oUserCurrent && $oTopic->getIsFavourite()}active{/if}"></div>
				<span class="favourite-count" id="fav_count_topic_{$oTopic->getId()}">{$oTopic->getCountFavourite()}</span>
			</li>
			
			{if $bTopicList}
				<li class="topic-info-comments">
					<a href="{$oTopic->getUrl()}#comments" title="{$aLang.topic_comment_read}">
                        <span class="link">{$oTopic->getCountComment()}</span>
                        <i class="icon-comment"></i>
                        {if $oTopic->getCountCommentNew()}<span>+{$oTopic->getCountCommentNew()}</span>{/if}
                    </a>
				</li>
			{/if}
			
			{hook run='topic_show_info' topic=$oTopic}
		</ul>

		
		{if !$bTopicList}
			{hook run='topic_show_end' topic=$oTopic}
		{/if}
	</footer>
</article> <!-- /.topic -->