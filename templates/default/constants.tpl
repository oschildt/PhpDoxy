<h1>{header_title}</h1>

<!-- if constants -->
  <table class="table table-responsive table-bordered table-striped multicolumn">
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

