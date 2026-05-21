package com.itsme.app.ui.orders
import android.content.Intent
import android.os.Bundle
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.FileProvider
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.itsme.app.data.entities.Article
import com.itsme.app.data.entities.Client
import com.itsme.app.data.entities.Order
import com.itsme.app.data.entities.OrderItem
import com.itsme.app.databinding.ActivityOrderDetailBinding
import com.itsme.app.databinding.DialogQuantityBinding
import com.itsme.app.utils.PdfGenerator
import com.itsme.app.viewmodel.ArticleViewModel
import com.itsme.app.viewmodel.ClientViewModel
import com.itsme.app.viewmodel.OrderViewModel
import kotlinx.coroutines.launch

class OrderDetailActivity : AppCompatActivity() {
    private lateinit var b: ActivityOrderDetailBinding
    private val vm: OrderViewModel by viewModels()
    private val cvm: ClientViewModel by viewModels()
    private val avm: ArticleViewModel by viewModels()
    private var oid = -1; private var cid = -1; private var tid = 0
    private var client: Client? = null
    private var articles = listOf<Article>()
    private lateinit var itemAdapter: OrderItemAdapter

    override fun onCreate(s: Bundle?) {
        super.onCreate(s)
        b = ActivityOrderDetailBinding.inflate(layoutInflater); setContentView(b.root)
        supportActionBar?.setDisplayHomeAsUpEnabled(true); supportActionBar?.title = "Detalle del Pedido"
        oid = intent.getIntExtra("oid", -1); cid = intent.getIntExtra("cid", -1); tid = intent.getIntExtra("tid", 0)
        avm.setTravel(tid)
        avm.articles.observe(this) { articles = it }

        itemAdapter = OrderItemAdapter(onDelete = { item ->
            AlertDialog.Builder(this).setTitle("Eliminar").setMessage("¿Eliminar '${item.articleName}'?")
                .setPositiveButton("Eliminar") { _,_ -> vm.deleteItem(item) }.setNegativeButton("Cancelar",null).show()
        })
        b.rvOrderItems.layoutManager = LinearLayoutManager(this)
        b.rvOrderItems.adapter = itemAdapter

        if (oid != -1) {
            // Load existing order
            vm.orders.observe(this) { list -> list.find { it.id == oid }?.let { updateTotal(it.totalAmount) } }
            cvm.all.observe(this) { list -> list.find { it.id == cid }?.let { c -> client = c; b.tvOrderClient.text = "Cliente: ${c.fullName}" } }
            loadItems(oid)
        } else {
            // New order — pick client first
            b.tvOrderClient.text = "Seleccione un cliente →"
            cvm.all.observe(this) { list ->
                if (list.isEmpty()) { Toast.makeText(this, "Cree un cliente primero", Toast.LENGTH_LONG).show(); finish(); return@observe }
                if (client != null) return@observe   // already chosen
                val names = list.map { it.fullName }.toTypedArray()
                AlertDialog.Builder(this).setTitle("Seleccionar Cliente").setItems(names) { _, idx ->
                    client = list[idx]
                    b.tvOrderClient.text = "Cliente: ${list[idx].fullName}"
                    lifecycleScope.launch {
                        val order = vm.getOrCreate(list[idx].id, tid)
                        oid = order.id
                        loadItems(oid)
                    }
                }.setNegativeButton("Cancelar") { _,_ -> finish() }.show()
            }
        }

        b.btnAddArticle.setOnClickListener { pickArticle() }
        b.btnGeneratePdf.setOnClickListener { genPdf() }
    }

    private fun loadItems(id: Int) {
        vm.items(id).observe(this) { items ->
            itemAdapter.submitList(items)
            updateTotal(items.sumOf { it.salePrice * it.quantity })
        }
    }

    private fun updateTotal(t: Double) { b.tvOrderTotal.text = "Total: \$${String.format("%.2f", t)}" }

    private fun pickArticle() {
        if (oid == -1) { Toast.makeText(this, "Seleccione un cliente primero", Toast.LENGTH_SHORT).show(); return }
        if (articles.isEmpty()) { Toast.makeText(this, "No hay artículos en este viaje", Toast.LENGTH_SHORT).show(); return }
        val names = articles.map { "${it.name}  (\$${String.format("%.2f",it.salePrice)})" }.toTypedArray()
        AlertDialog.Builder(this).setTitle("Agregar Artículo").setItems(names) { _, idx -> askQty(articles[idx]) }.show()
    }

    private fun askQty(a: Article) {
        val vb = DialogQuantityBinding.inflate(layoutInflater)
        AlertDialog.Builder(this).setTitle("Cantidad: ${a.name}").setView(vb.root)
            .setPositiveButton("Agregar") { _,_ ->
                val qty = vb.etQuantity.text.toString().toIntOrNull()?.coerceAtLeast(1) ?: 1
                vm.insertItem(OrderItem(orderId = oid, articleId = a.id, articleName = a.name, quantity = qty, salePrice = a.salePrice))
            }.setNegativeButton("Cancelar",null).show()
    }

    private fun genPdf() {
        val c = client ?: run { Toast.makeText(this, "Sin cliente asignado", Toast.LENGTH_SHORT).show(); return }
        lifecycleScope.launch {
            val items = itemAdapter.currentList
            val total = items.sumOf { it.salePrice * it.quantity }
            val file = PdfGenerator.generate(this@OrderDetailActivity, c, items, total, "Viaje #$tid")
            if (file != null) {
                val uri = FileProvider.getUriForFile(this@OrderDetailActivity, "${packageName}.fileprovider", file)
                val intent = Intent(Intent.ACTION_VIEW).apply {
                    setDataAndType(uri, "application/pdf")
                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                }
                startActivity(Intent.createChooser(intent, "Abrir PDF"))
            } else Toast.makeText(this@OrderDetailActivity, "Error al generar PDF", Toast.LENGTH_SHORT).show()
        }
    }
    override fun onSupportNavigateUp(): Boolean { finish(); return true }
}
