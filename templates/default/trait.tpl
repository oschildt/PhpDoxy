<h1>{header_title}</h1>

<!-- if class_labels -->
<!-- foreach class_labels -->
<span class="label {label_class}">{label_text}</span>
<!-- endforeach class_labels -->
<!-- endif class_labels -->

<div class="definition">{definition}</div>

<!-- if description -->
<div class="short-description">
{description}
</div>
<!-- endif description -->

<!-- if long_description -->
<div class="long_description">
<h2>Details</h2>
{long_description}
</div>
<!-- endif long_description -->

<!-- if internal -->
<h2>Internal usage</h2>
<div class="item_list">
{internal}
</div>
<!-- endif internal -->

<!-- if known_usages -->
<h2>Known usages</h2>
<div class="item_list">
<!-- foreach known_usages -->
{item}<br>
<!-- endforeach known_usages -->
</div>
<!-- endif known_usages -->

<!-- if authors -->
<h2>Authors</h2>
<div class="item_list">
<!-- foreach authors -->
<div class='item'>{author}</div>
<!-- endforeach authors -->
</div>
<!-- endif authors -->

<!-- if copyright -->
<h2>Copyright</h2>
<div class="item_list">
{copyright}
</div>
<!-- endif copyright -->

<!-- if license -->
<h2>License</h2>
<div class="item_list">
{license}
</div>
<!-- endif license -->

<!-- if package -->
<h2>Package</h2>
<div class="item_list">
{package}
</div>
<!-- endif package -->

<!-- if source-file -->
<h2>Source code</h2>
<div class="item_list">
{source-file}
</div>
<!-- endif source-file -->

<!-- if see -->
<h2>See also</h2>
<div class="item_list">
<!-- foreach see -->
{item}<br>
<!-- if description -->
<div class="item_description">{description}</div>
<!-- endif description -->
<!-- endforeach see -->
</div>
<!-- endif see -->

<!-- if links -->
<h2>Links</h2>
<div class="item_list">
<!-- foreach links -->
<a href="{url}" target="_blank">{url}</a><br>
<!-- if description -->
<div class="item_description">{description}</div>
<!-- endif description -->
<!-- endforeach links -->
</div>
<!-- endif links -->

<!-- if uses -->
<h2>Uses</h2>
<div class="item_list">
<!-- foreach uses -->
{item}<br>
<!-- if description -->
<div class="item_description">{description}</div>
<!-- endif description -->
<!-- endforeach uses -->
</div>
<!-- endif uses -->

<!-- if used_by -->
<h2>Used by</h2>
<div class="item_list">
<!-- foreach used_by -->
{item}<br>
<!-- if description -->
<div class="item_description">{description}</div>
<!-- endif description -->
<!-- endforeach used_by -->
</div>
<!-- endif used_by -->

<!-- if version -->
<h2>Version</h2>
{version}<br>
<!-- if version_description -->
<span class="item_description">{version_description}</span>
<!-- endif version_description -->
<!-- endif version -->

<!-- if since -->
<h2>Change log</h2>
<div class="item_list">
<ul>
<!-- foreach since -->
<li>{version}<br>
<!-- if description -->
<span class="item_description">{description}</span></li>
<!-- endif description -->
<!-- endforeach since -->
</ul>
</div>
<!-- endif since -->

<!-- if deprecated_version -->
<h2>Deprecated</h2>
<div class="item_list">
{deprecated_version}<br>
<!-- if deprecated_description -->
<div class="item_description">{deprecated_description}</div>
<!-- endif deprecated_description -->
</div>
<!-- endif deprecated_version -->

<!-- if todos -->
<h2>Todo</h2>
<div class="item_list">
<ul>
<!-- foreach todos -->
<li>{description}</li>
<!-- endforeach todos -->
</ul>
</div>
<!-- endif todos -->

<p>&nbsp;</p>

<!-- if methods -->
  <table class="table table-responsive table-bordered table-striped class_multicolumn">
      <tr><th colspan="2">Methods</th></tr>
      
      <!-- foreach methods -->
      <tr>
          <td>
              <div style='float:left'><a href="{object_link}">{object_name}</a></div>

              <div style='float:right'>
              <!-- if object_labels -->
              <!-- foreach object_labels -->
              <span class="label {object_label_class}">{object_label_text}</span>
              <!-- endforeach object_labels -->
              <!-- endif object_labels -->
              {object_visibility}
              </div>
              <div style="clear:both"></div>
          </td>
          <td class="description_column">
              {object_description}
          </td>
      </tr>
      <!-- endforeach methods -->
      
  </table>
<!-- endif methods -->

<!-- if detailed_methods -->
  <!-- foreach detailed_methods -->
  <hr id="{object_anchor}">
  
  <h1>{object_title}</h1>

  <div class="definition">{object_definition}</div>

  <!-- if object_description -->
  <div class="short-description">
  {object_description}
  </div>
  <!-- endif object_description -->

  <!-- if object_long_description -->
  <div class="long_description">
  <h2>Details</h2>
  {object_long_description}
  </div>
  <!-- endif object_long_description -->

  <!-- if object_internal -->
  <h2>Internal usage</h2>
  <div class="item_list">
  {object_internal}
  </div>
  <!-- endif object_internal -->

  <!-- if object_parameters -->
  <h2>Parameters</h2>

    <table class="table table-responsive table-bordered table-striped param_multicolumn">
        <tr>
        <th>Name</th>
        <th>Pass type</th>
        <th>Value type</th>
        <th>Default value</th>
        <th>Description</th>
        </tr>
        
        <!-- foreach object_parameters -->
        <tr>
            <td>
                {object_param_name}
            </td>
            <td>
                {object_pass_type}
            </td>
            <td>
                {object_param_type}
            </td>
            <td>
                {object_param_default}
            </td>
            <td class="description_column">
                {object_description}
            </td>
        </tr>
        <!-- endforeach object_parameters -->
        
    </table>
  <!-- endif object_parameters -->

  <!-- if object_return_type -->
  <h2>Returns</h2>
  <div class="item_list">
  {object_return_type}<br>
  <!-- if object_return_description -->
  <div class="item_description">{object_return_description}</div>
  <!-- endif object_return_description -->
  </div>
  <!-- endif object_return_type -->

  <!-- if object_throws -->
  <h2>Throws</h2>
  <div class="item_list">
  <!-- foreach object_throws -->
  {object_item}<br>
  <!-- if object_description -->
  <div class="item_description">{object_description}</div>
  <!-- endif object_description -->
  <!-- endforeach object_throws -->
  </div>
  <!-- endif object_throws -->

  <!-- if object_overrides -->
  <h2>Overrides</h2>
  <div class="item_list">
  {object_overrides}
  </div>
  <!-- endif object_overrides -->

  <!-- if object_authors -->
  <h2>Authors</h2>
  <div class="item_list">
  <!-- foreach object_authors -->
  <div class='item'>{object_author}</div>
  <!-- endforeach object_authors -->
  </div>
  <!-- endif object_authors -->

  <!-- if object_copyright -->
  <h2>Copyright</h2>
  <div class="item_list">
  {object_copyright}
  </div>
  <!-- endif object_copyright -->

  <!-- if object_license -->
  <h2>License</h2>
  <div class="item_list">
  {object_license}
  </div>
  <!-- endif object_license -->

  <!-- if object_source-file -->
  <h2>Source code</h2>
  <div class="item_list">
  {object_source-file}
  </div>
  <!-- endif object_source-file -->

  <!-- if object_see -->
  <h2>See also</h2>
  <div class="item_list">
  <!-- foreach object_see -->
  {object_item}<br>
  <!-- if object_description -->
  <div class="item_description">{object_description}</div>
  <!-- endif object_description -->
  <!-- endforeach object_see -->
  </div>
  <!-- endif object_see -->

  <!-- if object_links -->
  <h2>Links</h2>
  <div class="item_list">
  <!-- foreach object_links -->
  <a href="{object_url}" target="_blank">{object_url}</a><br>
  <!-- if object_description -->
  <div class="item_description">{object_description}</div>
  <!-- endif object_description -->
  <!-- endforeach object_links -->
  </div>
  <!-- endif object_links -->

  <!-- if object_uses -->
  <h2>Uses</h2>
  <div class="item_list">
  <!-- foreach object_uses -->
  {object_item}<br>
  <!-- if object_description -->
  <div class="item_description">{object_description}</div>
  <!-- endif object_description -->
  <!-- endforeach object_uses -->
  </div>
  <!-- endif object_uses -->

  <!-- if object_used_by -->
  <h2>Used by</h2>
  <div class="item_list">
  <!-- foreach object_used_by -->
  {object_item}<br>
  <!-- if object_description -->
  <div class="item_description">{object_description}</div>
  <!-- endif object_description -->
  <!-- endforeach object_used_by -->
  </div>
  <!-- endif object_used_by -->

  <!-- if object_version -->
  <h2>Version</h2>
  {object_version}<br>
  <!-- if object_version_description -->
  <span class="item_description">{object_version_description}</span>
  <!-- endif object_version_description -->
  <!-- endif object_version -->

  <!-- if object_since -->
  <h2>Change log</h2>
  <div class="item_list">
  <ul>
  <!-- foreach object_since -->
  <li>{object_version}<br>
  <!-- if object_description -->
  <span class="item_description">{object_description}</span></li>
  <!-- endif object_description -->
  <!-- endforeach object_since -->
  </ul>
  </div>
  <!-- endif object_since -->

  <!-- if object_deprecated_version -->
  <h2>Deprecated</h2>
  <div class="item_list">
  {object_deprecated_version}<br>
  <!-- if object_deprecated_description -->
  <div class="item_description">{object_deprecated_description}</div>
  <!-- endif object_deprecated_description -->
  </div>
  <!-- endif object_deprecated_version -->

  <!-- if object_todos -->
  <h2>Todo</h2>
  <div class="item_list">
  <ul>
  <!-- foreach object_todos -->
  <li>{object_description}</li>
  <!-- endforeach object_todos -->
  </ul>
  </div>
  <!-- endif object_todos -->
  
  <!-- endforeach detailed_methods -->
<!-- endif detailed_methods -->

