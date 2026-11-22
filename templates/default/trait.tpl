<h1>{header_title}</h1>

<!-- if class_labels -->
<!-- foreach class_labels -->
<span class="label {label_class}">{label_text}</span>
<!-- endforeach class_labels -->
<!-- endif class_labels -->

<div class="definition">{definition}</div>
{type_parameters}

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
{tag_link}<br>
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

<!-- if deprecated -->
<h2>Deprecated</h2>
<div class="item_list">
<!-- if deprecated_version -->
{deprecated_version}<br>
<!-- endif deprecated_version -->
<!-- if deprecated_description -->
<div class="item_description">{deprecated_description}</div>
<!-- endif deprecated_description -->
</div>
<!-- endif deprecated -->

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

{members}

