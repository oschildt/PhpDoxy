<h1>{header_title}</h1>

<!-- if interfaces -->
  <table class="table table-responsive table-bordered table-striped multicolumn">
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
