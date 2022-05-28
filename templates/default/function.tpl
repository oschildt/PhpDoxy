<h1>{header_title}</h1>

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

<!-- if parameters -->
<h2>Parameters</h2>

  <table class="table table-responsive table-bordered table-striped param_multicolumn">
      <tr>
      <th>Name</th>
      <th>Pass type</th>
      <th>Value type</th>
      <th>Default value</th>
      <th>Description</th>
      </tr>
      
      <!-- foreach parameters -->
      <tr>
          <td>
              {param_name}
          </td>
          <td>
              {pass_type}
          </td>
          <td>
              {param_type}
          </td>
          <td>
              {param_default}
          </td>
          <td class="description_column">
              {description}
          </td>
      </tr>
      <!-- endforeach parameters -->
      
  </table>
<!-- endif parameters -->

<!-- if return_type -->
<h2>Returns</h2>
<div class="item_list">
{return_type}<br>
<!-- if return_description -->
<div class="item_description">{return_description}</div>
<!-- endif return_description -->
</div>
<!-- endif return_type -->

<!-- if throws -->
<h2>Throws</h2>
<div class="item_list">
<!-- foreach throws -->
{item}<br>
<!-- if description -->
<div class="item_description">{description}</div>
<!-- endif description -->
<!-- endforeach throws -->
</div>
<!-- endif throws -->

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
