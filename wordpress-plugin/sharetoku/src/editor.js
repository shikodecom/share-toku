import { registerBlockType } from '@wordpress/blocks';
import { createElement, useState, useEffect } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';

(function (wp) {
  'use strict';
  var el = wp.element.createElement;
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var apiFetch = wp.apiFetch;
  wp.blocks.registerBlockType(metadata.name, {
    ...metadata,
    edit: function (props) {
      var attrs = props.attributes;
      var state = useState(''); var query = state[0], setQuery = state[1];
      var results = useState([]); var offers = results[0], setOffers = results[1];
      var loadingState = useState(false); var loading = loadingState[0], setLoading = loadingState[1];
      var errorState = useState(''); var error = errorState[0], setError = errorState[1];
      var previewState = useState(''); var preview = previewState[0], setPreview = previewState[1];
      var planState = useState(null); var plan = planState[0], setPlan = planState[1];
      useEffect(function () {
        apiFetch({path:'/sharetoku/v1/site'}).then(function (site) {
          setPlan(site.connected ? site.plan : 'disconnected');
        }).catch(function () { setPlan('unavailable'); });
      }, []);
      useEffect(function () {
        if (!attrs.placementKey) props.setAttributes({placementKey:'block-' + crypto.randomUUID()});
      }, []);
      useEffect(function () {
        var active = true; setLoading(true);
        var timer = setTimeout(function () {
          apiFetch({path:'/sharetoku/v1/offers?q=' + encodeURIComponent(query)}).then(function (data) {
            if (active) { setOffers(data.data || []); setError(''); setLoading(false); }
          }).catch(function () { if (active) { setError('ShareToku に接続できません。設定を確認してください。'); setLoading(false); } });
        }, 250);
        return function () { active=false; clearTimeout(timer); };
      }, [query]);
      useEffect(function () {
        if (!attrs.offerId || !attrs.placementKey) return;
        apiFetch({path:'/sharetoku/v1/preview', method:'POST', data:{offerId:attrs.offerId, placementKey:attrs.placementKey}})
          .then(function (data) { setPreview(data.html || ''); }).catch(function () { setPreview('プレビューを取得できません。'); });
      }, [attrs.offerId, attrs.placementKey]);
      return el('div', wp.blockEditor.useBlockProps(),
        el('label', {htmlFor:'sharetoku-search-' + props.clientId}, '紹介特典を検索'),
        el('input', {id:'sharetoku-search-' + props.clientId, type:'search', value:query, onChange:function(e){setQuery(e.target.value);}}),
        loading ? el('p', null, '検索中…') : null,
        plan === 'disconnected' ? el('p', {role:'alert'}, 'ShareToku が未接続です。設定画面から接続してください。') : null,
        plan === 'unavailable' ? el('p', {role:'alert'}, 'ShareToku に接続できません。しばらくしてから再試行してください。') : null,
        error ? el('p', {role:'alert'}, error) : null,
        el('ul', null, offers.map(function (offer) { return el('li', {key:offer.public_id},
          el('button', {type:'button', 'aria-pressed':attrs.offerId === offer.public_id,
            onClick:function(){props.setAttributes({offerId:offer.public_id});}},
            offer.service.name + ' / ' + (offer.program ? offer.program.name + ' / ' : '') + offer.public_id)); })),
        attrs.offerId ? el('p', null, '選択中: ' + attrs.offerId) : null,
        plan === 'free' ? el('p', null, 'FREE プランでは公開時に関連する ShareToku 運営者の別サービス特典が追加表示される場合があります。') : null,
        preview ? el('div', {dangerouslySetInnerHTML:{__html:preview}}) : null
      );
    },
    save: function () { return null; }
  });
})({ blocks: { registerBlockType }, element: { createElement, useState, useEffect }, blockEditor: { useBlockProps }, apiFetch });
