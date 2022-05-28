<h1>{header_title}</h1>

<!-- if functions -->
  <table class="table table-responsive table-bordered table-striped multicolumn">
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
