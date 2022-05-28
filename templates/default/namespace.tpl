<h1>{header_title}</h1>

<ul id="index">
<!-- if index_namespaces -->
<li><a href="#namespaces">Namespaces</a></li>
<!-- endif index_namespaces -->

<!-- if index_constants -->
<li><a href="#constants">Constants</a></li>
<!-- endif index_constants -->

<!-- if index_functions -->
<li><a href="#functions">Functions</a></li>
<!-- endif index_functions -->

<!-- if index_interfaces -->
<li><a href="#interfaces">Interfaces</a></li>
<!-- endif index_interfaces -->

<!-- if index_classes -->
<li><a href="#classes">Classes</a></li>
<!-- endif index_classes -->

<!-- if index_traits -->
<li><a href="#traits">Traits</a></li>
<!-- endif index_traits -->
</ul>

<!-- if namespaces -->
  <table id="namespaces" class="table table-responsive table-bordered table-striped">
      <tr><th>Namespaces</th></tr>
      
      <!-- foreach namespaces -->
      <tr>
          <td>
              <a href="{namespace_link}">{namespace_name}</a>
          </td>
      </tr>
      <!-- endforeach namespaces -->
      
  </table>
<!-- endif namespaces -->

<!-- if constants -->
  <table id="constants" class="table table-responsive table-bordered table-striped multicolumn">
      <tr><th colspan="3">Constants</th></tr>
      
      <!-- foreach constants -->
      <tr>
          <td>
              <a href="{constant_link}">{constant_name}</a>
          </td>
          <td class="namespace_column">
              <!-- if namespace -->
              <a href="{namespace_link}">{namespace}</a>
              <!-- endif namespace -->
          </td>
          <td class="description_column">
              {constant_description}
          </td>
      </tr>
      <!-- endforeach constants -->
      
  </table>
<!-- endif constants -->

<!-- if functions -->
  <table id="functions" class="table table-responsive table-bordered table-striped multicolumn">
      <tr><th colspan="3">Functions</th></tr>
      
      <!-- foreach functions -->
      <tr>
          <td>
              <a href="{function_link}">{function_name}</a>
          </td>
          <td class="namespace_column">
              <!-- if namespace -->
              <a href="{namespace_link}">{namespace}</a>
              <!-- endif namespace -->
          </td>
          <td class="description_column">
              {function_description}
          </td>
      </tr>
      <!-- endforeach functions -->
      
  </table>
<!-- endif functions -->

<!-- if interfaces -->
  <table id="interfaces" class="table table-responsive table-bordered table-striped multicolumn">
      <tr><th colspan="3">Interfaces</th></tr>
      
      <!-- foreach interfaces -->
      <tr>
          <td>
              <a href="{interface_link}">{interface_name}</a>
          </td>
          <td class="namespace_column">
              <!-- if namespace -->
              <a href="{namespace_link}">{namespace}</a>
              <!-- endif namespace -->
          </td>
          <td class="description_column">
              {interface_description}
          </td>
      </tr>
      <!-- endforeach interfaces -->
      
  </table>
<!-- endif interfaces -->

<!-- if classes -->
  <table id="classes" class="table table-responsive table-bordered table-striped multicolumn">
      <tr><th colspan="3">Classes</th></tr>
      
      <!-- foreach classes -->
      <tr>
          <td>
              <a href="{class_link}">{class_name}</a>
          </td>
          <td class="namespace_column">
              <!-- if namespace -->
              <a href="{namespace_link}">{namespace}</a>
              <!-- endif namespace -->
          </td>
          <td class="description_column">
              {class_description}
          </td>
      </tr>
      <!-- endforeach classes -->
      
  </table>
<!-- endif classes -->

<!-- if traits -->
  <table id="traits" class="table table-responsive table-bordered table-striped multicolumn">
      <tr><th colspan="3">Traits</th></tr>
      
      <!-- foreach traits -->
      <tr>
          <td>
              <a href="{trait_link}">{trait_name}</a>
          </td>
          <td class="namespace_column">
              <!-- if namespace -->
              <a href="{namespace_link}">{namespace}</a>
              <!-- endif namespace -->
          </td>
          <td class="description_column">
              {trait_description}
          </td>
      </tr>
      <!-- endforeach traits -->
      
  </table>
<!-- endif traits -->