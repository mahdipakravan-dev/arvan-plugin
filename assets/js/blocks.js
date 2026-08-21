(function (blocks, element, components, blockEditor, i18n) {
  'use strict';

  var el = element.createElement;
  var InspectorControls = blockEditor.InspectorControls;
  var PanelBody = components.PanelBody;
  var TextControl = components.TextControl;
  var ToggleControl = components.ToggleControl;
  var __ = i18n.__;

  blocks.registerBlockType('acr/product-catalog', {
    title: __('کاتالوگ محصولات آروان', 'arvancloud-reseller'),
    description: __('نمایش محصولات و قیمت‌های همگام‌شده در فرانت‌آفیس.', 'arvancloud-reseller'),
    icon: 'cloud',
    category: 'widgets',
    attributes: {
      title: { type: 'string', default: 'محصولات ابری' },
      showSyncTime: { type: 'boolean', default: true },
      showPolicyNotice: { type: 'boolean', default: true }
    },
    edit: function (props) {
      var attrs = props.attributes;
      return el(
        element.Fragment,
        null,
        el(InspectorControls, null,
          el(PanelBody, { title: __('تنظیمات نمایش', 'arvancloud-reseller'), initialOpen: true },
            el(TextControl, { label: __('عنوان', 'arvancloud-reseller'), value: attrs.title, onChange: function (value) { props.setAttributes({ title: value }); } }),
            el(ToggleControl, { label: __('نمایش زمان بروزرسانی', 'arvancloud-reseller'), checked: attrs.showSyncTime, onChange: function (value) { props.setAttributes({ showSyncTime: value }); } }),
            el(ToggleControl, { label: __('نمایش شرایط قطع سرویس', 'arvancloud-reseller'), checked: attrs.showPolicyNotice, onChange: function (value) { props.setAttributes({ showPolicyNotice: value }); } })
          )
        ),
        el('div', { className: 'acr-editor-preview' },
          el('span', { className: 'dashicons dashicons-cloud' }),
          el('div', null, el('strong', null, attrs.title), el('p', null, __('کارت‌های CDN، سرور ابری و فضای ابری از کاتالوگ همگام‌شده نمایش داده می‌شوند.', 'arvancloud-reseller')))
        )
      );
    },
    save: function () { return null; }
  });

  blocks.registerBlockType('acr/customer-profile', {
    title: __('پروفایل مشتری آروان', 'arvancloud-reseller'),
    description: __('ورود وردپرس، کیف پول، سفارش و سرویس‌های مشتری در یک پنل.', 'arvancloud-reseller'),
    icon: 'admin-users',
    category: 'widgets',
    edit: function () {
      return el('div', { className: 'acr-editor-preview' },
        el('span', { className: 'dashicons dashicons-admin-users' }),
        el('div', null, el('strong', null, __('پروفایل یکپارچه مشتری', 'arvancloud-reseller')), el('p', null, __('در فرانت، وضعیت ورود، موجودی کیف پول و فرم سفارش نمایش داده می‌شود.', 'arvancloud-reseller')))
      );
    },
    save: function () { return null; }
  });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n);

