package com.itsme.app.ui.orders
import android.content.Intent
import android.os.Bundle
import android.view.*
import androidx.appcompat.app.AlertDialog
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.itsme.app.databinding.FragmentOrdersBinding
import com.itsme.app.utils.SessionManager
import com.itsme.app.viewmodel.ClientViewModel
import com.itsme.app.viewmodel.OrderViewModel

class OrdersFragment : Fragment() {
    private var _b: FragmentOrdersBinding? = null
    private val b get() = _b!!
    private val vm: OrderViewModel by viewModels()
    private val cvm: ClientViewModel by viewModels()
    private var cmap = mapOf<Int, String>()

    override fun onCreateView(i: LayoutInflater, c: ViewGroup?, s: Bundle?) =
        FragmentOrdersBinding.inflate(i, c, false).also { _b = it }.root

    override fun onViewCreated(v: View, s: Bundle?) {
        val tid = SessionManager.getTravelId(requireContext())
        vm.setTravel(tid)
        cvm.all.observe(viewLifecycleOwner) { list -> cmap = list.associate { it.id to it.fullName }; }
        val adapter = OrderAdapter(
            getName = { id -> cmap[id] ?: "Cliente #$id" },
            onOpen = { o -> startActivity(Intent(requireContext(), OrderDetailActivity::class.java).apply {
                putExtra("oid", o.id); putExtra("cid", o.clientId); putExtra("tid", o.travelDateId) }) },
            onDelete = { o -> AlertDialog.Builder(requireContext()).setTitle("Eliminar pedido")
                .setMessage("¿Eliminar este pedido?")
                .setPositiveButton("Eliminar") { _,_ -> vm.deleteOrder(o) }
                .setNegativeButton("Cancelar",null).show() }
        )
        b.rvOrders.layoutManager = LinearLayoutManager(requireContext())
        b.rvOrders.adapter = adapter
        vm.orders.observe(viewLifecycleOwner) { adapter.submitList(it) }
        b.fabAddOrder.setOnClickListener {
            startActivity(Intent(requireContext(), OrderDetailActivity::class.java).apply {
                putExtra("oid", -1); putExtra("tid", tid) })
        }
    }
    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
