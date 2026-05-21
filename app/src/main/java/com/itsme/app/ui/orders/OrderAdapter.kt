package com.itsme.app.ui.orders
import android.view.*
import androidx.recyclerview.widget.*
import com.itsme.app.data.entities.Order
import com.itsme.app.databinding.ItemOrderBinding

class OrderAdapter(val getName:(Int)->String, val onOpen:(Order)->Unit, val onDelete:(Order)->Unit) :
    ListAdapter<Order, OrderAdapter.VH>(object: DiffUtil.ItemCallback<Order>() {
        override fun areItemsTheSame(a: Order, b: Order) = a.id==b.id
        override fun areContentsTheSame(a: Order, b: Order) = a==b
    }) {
    inner class VH(val b: ItemOrderBinding) : RecyclerView.ViewHolder(b.root)
    override fun onCreateViewHolder(p: ViewGroup, t: Int) = VH(ItemOrderBinding.inflate(LayoutInflater.from(p.context), p, false))
    override fun onBindViewHolder(h: VH, pos: Int) {
        val o = getItem(pos)
        h.b.tvOrderClient.text = getName(o.clientId)
        h.b.tvOrderTotal.text = "Total: \$${String.format("%.2f", o.totalAmount)}"
        h.b.root.setOnClickListener { onOpen(o) }
        h.b.btnDelete.setOnClickListener { onDelete(o) }
    }
}
